// Mediablue ネイティブ版 (Tauri / WebKitGTK)
//
// 起動時に PHP の組み込みサーバーを 127.0.0.1 で立ち上げ、
// その画面を WebKitGTK のウィンドウで表示する。
// ウィンドウを閉じる(アプリ終了)と PHP サーバーも停止する。
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

use std::net::{SocketAddr, TcpListener, TcpStream};
use std::path::{Path, PathBuf};
use std::process::{Child, Command, Stdio};
use std::sync::Mutex;
use std::time::{Duration, Instant};

use tauri::{Manager, RunEvent, WebviewUrl, WebviewWindowBuilder};

/// Docker版(12080)と衝突しないよう、ネイティブ版は別ポートを優先して使う。
/// 固定ポートにしておくと、言語設定などの localStorage が起動ごとに消えない。
const PREFERRED_PORT: u16 = 12180;

/// PWA 用 Service Worker はネイティブ版では不要なので無効化する。
/// ('serviceWorker' in navigator が false になり、app.js は「非対応」として扱う)
const INIT_SCRIPT: &str = r#"
  try { delete Navigator.prototype.serviceWorker; } catch (e) {}
  window.__MEDIABLUE_NATIVE__ = true;
"#;

struct PhpServer(Mutex<Option<Child>>);

fn main() {
    // 一部の GPU / VM 環境で WebKitGTK の画面が真っ白になる既知の問題の回避策
    if std::env::var_os("WEBKIT_DISABLE_DMABUF_RENDERER").is_none() {
        std::env::set_var("WEBKIT_DISABLE_DMABUF_RENDERER", "1");
    }

    let fullscreen = std::env::args().any(|a| a == "--fullscreen" || a == "-f")
        || env_flag("MEDIABLUE_FULLSCREEN");

    let app = tauri::Builder::default()
        .manage(PhpServer(Mutex::new(None)))
        .setup(move |app| {
            let handle = app.handle();
            let url = match start_php(handle) {
                Ok((child, port)) => {
                    *app.state::<PhpServer>().0.lock().unwrap() = Some(child);
                    WebviewUrl::External(
                        format!("http://127.0.0.1:{port}/").parse().unwrap(),
                    )
                }
                Err(msg) => {
                    eprintln!("[mediablue] {msg}");
                    WebviewUrl::App(format!("index.html#{}", encode(&msg)).into())
                }
            };

            WebviewWindowBuilder::new(app, "main", url)
                .title("Mediablue")
                .inner_size(1280.0, 720.0)
                .min_inner_size(800.0, 480.0)
                .fullscreen(fullscreen)
                .initialization_script(INIT_SCRIPT)
                .build()?;
            Ok(())
        })
        .build(tauri::generate_context!())
        .expect("failed to build Mediablue");

    app.run(|handle, event| {
        if let RunEvent::Exit = event {
            stop_php(handle);
        }
    });
}

/// PHP 組み込みサーバーを起動し、応答するまで待つ。
fn start_php(handle: &tauri::AppHandle) -> Result<(Child, u16), String> {
    let web_root = find_web_root(handle).ok_or_else(|| {
        "アプリのファイル(web/public)が見つかりません。\n再インストールしてください。".to_string()
    })?;
    let public_dir = web_root.join("public");
    let router = public_dir.join("index.php");

    let php = std::env::var("MEDIABLUE_PHP").unwrap_or_else(|_| "php".into());
    let port = std::env::var("MEDIABLUE_PORT")
        .ok()
        .and_then(|p| p.parse().ok())
        .filter(|p| port_is_free(*p))
        .or_else(|| port_is_free(PREFERRED_PORT).then_some(PREFERRED_PORT))
        .or_else(random_free_port)
        .ok_or("空きポートが見つかりません。")?;

    let mut cmd = Command::new(&php);
    cmd.arg("-S")
        .arg(format!("127.0.0.1:{port}"))
        .arg("-t")
        .arg(&public_dir)
        .arg(&router)
        .current_dir(&web_root)
        .stdin(Stdio::null());

    // ログを出したいときは MEDIABLUE_DEBUG=1 で起動
    if !env_flag("MEDIABLUE_DEBUG") {
        cmd.stdout(Stdio::null()).stderr(Stdio::null());
    }

    // Docker版では固定値だった PULSE_SERVER を、実行ユーザーの環境から決める
    if std::env::var_os("PULSE_SERVER").is_none() {
        if let Some(rt) = std::env::var_os("XDG_RUNTIME_DIR") {
            let sock = Path::new(&rt).join("pulse/native");
            if sock.exists() {
                cmd.env("PULSE_SERVER", format!("unix:{}", sock.display()));
            }
        }
    }

    // アプリが強制終了・クラッシュした場合も PHP が残らないよう、
    // 親プロセスの終了時に SIGTERM を受け取るよう設定する (Linux)
    #[cfg(target_os = "linux")]
    unsafe {
        use std::os::unix::process::CommandExt;
        cmd.pre_exec(|| {
            if libc::prctl(libc::PR_SET_PDEATHSIG, libc::SIGTERM) != 0 {
                return Err(std::io::Error::last_os_error());
            }
            Ok(())
        });
    }

    let mut child = cmd.spawn().map_err(|e| {
        format!(
            "PHP を起動できませんでした ({php}): {e}\n\n\
             次のコマンドで PHP をインストールしてください:\n  sudo apt install php-cli"
        )
    })?;

    // サーバーが応答するまで最大 8 秒待つ
    let addr: SocketAddr = ([127, 0, 0, 1], port).into();
    let deadline = Instant::now() + Duration::from_secs(8);
    loop {
        if TcpStream::connect_timeout(&addr, Duration::from_millis(200)).is_ok() {
            return Ok((child, port));
        }
        if let Ok(Some(status)) = child.try_wait() {
            return Err(format!(
                "PHP サーバーが終了しました ({status})。\n\
                 MEDIABLUE_DEBUG=1 mediablue で起動するとログを確認できます。"
            ));
        }
        if Instant::now() > deadline {
            let _ = child.kill();
            return Err("PHP サーバーが時間内に応答しませんでした。".into());
        }
        std::thread::sleep(Duration::from_millis(100));
    }
}

fn stop_php(handle: &tauri::AppHandle) {
    if let Some(mut child) = handle.state::<PhpServer>().0.lock().unwrap().take() {
        let _ = child.kill();
        let _ = child.wait();
    }
}

/// .deb インストール時は /usr/lib/Mediablue/web、開発時はリポジトリ直下を探す
fn find_web_root(handle: &tauri::AppHandle) -> Option<PathBuf> {
    let mut candidates = Vec::new();
    if let Ok(dir) = std::env::var("MEDIABLUE_WEB_ROOT") {
        candidates.push(PathBuf::from(dir));
    }
    if let Ok(res) = handle.path().resource_dir() {
        candidates.push(res.join("web"));
    }
    if cfg!(debug_assertions) {
        candidates.push(PathBuf::from(env!("CARGO_MANIFEST_DIR")).join(".."));
    }
    candidates
        .into_iter()
        .find(|d| d.join("public/index.php").is_file() && d.join("src/App.php").is_file())
}

fn port_is_free(port: u16) -> bool {
    TcpListener::bind(("127.0.0.1", port)).is_ok()
}

fn random_free_port() -> Option<u16> {
    TcpListener::bind(("127.0.0.1", 0))
        .ok()
        .and_then(|l| l.local_addr().ok())
        .map(|a| a.port())
}

fn env_flag(name: &str) -> bool {
    matches!(
        std::env::var(name).as_deref(),
        Ok("1") | Ok("true") | Ok("yes")
    )
}

/// URL フラグメント用の簡易エンコード
fn encode(s: &str) -> String {
    s.bytes()
        .map(|b| match b {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => {
                (b as char).to_string()
            }
            _ => format!("%{b:02X}"),
        })
        .collect()
}
