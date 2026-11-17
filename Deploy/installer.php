<?php session_start();
define('DUPLICATOR_INSTALLER', "1.1");
set_time_limit(300);

/**
 * Class Installer
 *
 * @category
 * @author flydev
 *
 * This script contains code available in the install.php of ProcessWire.
 */

class Installer
{
  /**
   *
   */
  const TEST_MODE = false;
  /**
   *
   */
  const MIN_REQUIRED_PHP_VERSION = "5.4.0";
  /**
   *
   */
  const DUP_TEMP_FOLDER = 'duplicator-temp';
  /**
   *
   */
  const DUP_PACKAGE_EXTENSION = 'package.zip';
  const DUP_PACKAGE2_EXTENSION = 'package2.zip';    //ATO:
  /**
   *
   */
  const DUP_PW_EXTENSION = 'pw.zip';
  /**
   *
   */
  const DUP_SQL_EXTENSION = 'sql.zip';

  /**
   * @var string
   */
  protected $chmodDir = "0777";
  /**
   * @var string
   */
  protected $chmodFile = "0666";
  /**
   * @var
   */
  protected $rootPath;
  /**
   * @var
   */
  protected $package;
  /**
   * @var
   */
  protected $h;
  /**
   * @var int
   */
  protected $numErrors = 0;


  /**
   * Check if the given function $name exists and report OK or fail with $label
   *
   * @param string $name
   * @param string $label
   *
   */
  protected function checkFunction($name, $label)
  {
    if (function_exists($name)) $this->ok("$label");
    else $this->err("Fail: $label");
  }

  /**
   *
   */
  public function execute()
  {

    if (self::TEST_MODE) {
      error_reporting(E_ALL | E_STRICT);
      ini_set('display_errors', 1);
    }

    $this->renderHead();

    if (isset($_POST['step'])) switch ($_POST['step']) {

      case 0:
        $this->compatibilityCheck();
        break;

      case 1:
        $this->scanDir();
        break;

      case 2:
        $this->extractPackage();
        break;

      case 3:
        $this->makeDatabase();
        break;

      case 4:
        $this->dbSaveConfig();
        break;

        //case 5: $this->AdjustConfigPhp(); break;

      default:
        $this->welcome();
        break;
    }
    else $this->welcome();

    $this->renderFooter();
  }


  /**
   * Welcome/Intro screen
   *
   */
  protected function welcome()
  {
    $this->h("Welcome. This tool will guide you through the cloning process.");
    $this->p("Thanks for choosing Duplicator as your backup utility! <strong>We recommend you to make a backup of any existing file and database before proceeding.</strong> This process will overwrite files and create/replace the database. If you need help or have questions during the cloning operation, please stop by our <a href='https://processwire.com/talk/' target='_blank'>support thread</a> and we'll be glad to help.");
    $this->btn("Get Started", 0, 'sign-in');
  }


  /**
   * Installer steps, as shown in the progress bar (index = posted step + 1)
   *
   */
  protected static $stepLabels = array('Check', 'Package', 'Extract', 'Database', 'Finish');

  /**
   * Inline SVG icon (no external font or CDN: the installer often runs on a server without them)
   *
   * @param string $name
   * @return string
   *
   */
  protected function icon($name)
  {
    $paths = array(
      'arrow' => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
      'refresh' => '<path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/>',
      'check' => '<path d="M5 12.5l4.5 4.5L19 7"/>',
      'warning' => '<path d="M12 3.5L22 20.5H2z"/><path d="M12 10v4.5"/><path d="M12 17.5v.01"/>',
      'error' => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6"/><path d="M15 9l-6 6"/>',
      'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5v.01"/>',
      'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3c3 3.5 3 14.5 0 18"/><path d="M12 3c-3 3.5-3 14.5 0 18"/>',
      'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.6 2.2c-.7.4-1.1 1-1.1 1.8v.5"/><path d="M12 17.5v.01"/>',
      'start' => '<path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/>',
    );
    // names used by older calls (Font Awesome)
    $aliases = array('angle-right' => 'arrow', 'sign-in' => 'start', 'exclamation-triangle' => 'warning', 'info-circle' => 'info', 'comments' => 'help');
    if (isset($aliases[$name])) $name = $aliases[$name];
    if (!isset($paths[$name])) $name = 'arrow';
    return "<svg class='icon' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>{$paths[$name]}</svg>";
  }

  /**
   *
   */
  protected function renderHead()
  {
    if (!defined("DUPLICATOR_INSTALLER")) die("error.");
    $title = "Duplicator installer";
    $formAction = "./installer.php";
    $current = isset($_POST['step']) ? ((int) $_POST['step']) + 1 : 0;

    $steps = '';
    foreach (self::$stepLabels as $i => $label) {
      $n = $i + 1;
      $state = $n < $current ? 'done' : ($n === $current ? 'current' : 'todo');
      $aria = $state === 'current' ? " aria-current='step'" : '';
      $steps .= "<li class='step $state'$aria><span class='step-label'>$label</span></li>";
    }

    echo "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='utf-8' />
<meta name='viewport' content='width=device-width, initial-scale=1' />
<meta name='robots' content='noindex, nofollow' />
<title>" . htmlentities($title, ENT_QUOTES, 'UTF-8') . "</title>
<style>
:root {
  color-scheme: light dark;
  --bg: #f3f5f8;
  --surface: #ffffff;
  --text: #1c2733;
  --muted: #5b6b7c;
  --border: #dde3ea;
  --accent: #2459d6;
  --accent-text: #ffffff;
  --accent-soft: #e8eefc;
  --ok: #1d7a46;
  --ok-soft: #e6f4ec;
  --warn: #9a5b00;
  --warn-soft: #fdf3e1;
  --err: #b42318;
  --err-soft: #fdecea;
  --radius: 10px;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #0f141a;
    --surface: #171e26;
    --text: #e3e9ef;
    --muted: #93a2b1;
    --border: #2a3542;
    --accent: #6b93f5;
    --accent-text: #0b1220;
    --accent-soft: #1c2943;
    --ok: #5cc68b;
    --ok-soft: #142a1e;
    --warn: #f0b350;
    --warn-soft: #2c2312;
    --err: #f38b80;
    --err-soft: #321a18;
  }
}
* { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font: 15px/1.55 system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}
a { color: var(--accent); }
code { font: 0.92em ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; background: var(--accent-soft); padding: 0.1em 0.35em; border-radius: 4px; }
.icon { width: 1.15em; height: 1.15em; flex: none; vertical-align: -0.2em; }
.wrap { max-width: 760px; margin: 0 auto; padding: 0 16px; }

.top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1.25rem 0 1rem; }
.brand { display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 1.1rem; letter-spacing: -0.01em; }
.brand-mark { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 8px; background: var(--accent); color: var(--accent-text); font-size: 1rem; }
.brand small { font-weight: 500; color: var(--muted); font-size: 0.85rem; }
.help { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.9rem; text-decoration: none; }
.help:hover { text-decoration: underline; }

.steps { display: flex; gap: 0.25rem; list-style: none; margin: 0 0 1rem; padding: 0; counter-reset: step; }
.step { flex: 1; display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.8rem; color: var(--muted); }
.step::before { content: ''; height: 4px; border-radius: 2px; background: var(--border); }
.step.done::before, .step.current::before { background: var(--accent); }
.step.current { color: var(--text); font-weight: 600; }

.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 1.5rem; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); }
.eyebrow { margin: 0 0 0.15rem; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--accent); }
h2 { margin: 0 0 1rem; font-size: 1.4rem; line-height: 1.25; letter-spacing: -0.01em; }
p { margin: 0 0 1rem; }
.detail { color: var(--muted); font-size: 0.875rem; }

.msg { display: flex; gap: 0.6rem; align-items: flex-start; margin: 0 0 0.4rem; padding: 0.5rem 0.75rem; border-radius: 8px; overflow-wrap: anywhere; }
.msg .icon { margin-top: 0.15em; }
.msg-ok { background: none; padding: 0.15rem 0; margin: 0 0 0.15rem; }
.msg-ok .icon { color: var(--ok); }
.msg-warn { background: var(--warn-soft); }
.msg-warn .icon { color: var(--warn); }
.msg-err { background: var(--err-soft); font-weight: 600; }
.msg-err .icon { color: var(--err); }
.msg-info { background: var(--accent-soft); }
.msg-info .icon { color: var(--accent); }
.msg + p, .msg + .fields { margin-top: 1rem; }
.btn + p, div:has(> .btn) + p { margin-top: 0.75rem; color: var(--muted); font-size: 0.875rem; }

.fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: 0.75rem 1rem; margin: 0 0 1rem; }
.field { display: flex; flex-direction: column; gap: 0.3rem; min-width: 0; font-size: 0.875rem; font-weight: 600; }
.field.wide { grid-column: 1 / -1; }
.field small { font-weight: 400; color: var(--muted); }
input[type=text], input[type=password], input[type=email], input[type=number], select {
  width: 100%;
  max-width: 100%;
  font: inherit;
  font-weight: 400;
  font-size: 0.95rem;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.5rem 0.65rem;
}
input:focus-visible, select:focus-visible, .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

.btn {
  display: inline-flex; align-items: center; gap: 0.45rem;
  margin: 0.75rem 0.5rem 0 0; padding: 0.6rem 1.1rem;
  font: inherit; font-weight: 600; font-size: 0.95rem; text-decoration: none;
  color: var(--accent-text); background: var(--accent);
  border: 1px solid var(--accent); border-radius: 8px; cursor: pointer;
}
.btn:hover { filter: brightness(1.08); }
.btn.secondary { color: var(--accent); background: transparent; }
.btn.secondary:hover { background: var(--accent-soft); filter: none; }
.btn .icon { order: 2; }
.btn.icon-left .icon { order: 0; }

.foot { padding: 1.25rem 0 2rem; color: var(--muted); font-size: 0.85rem; }
.foot a { color: inherit; }
@media (max-width: 520px) {
  .card { padding: 1.1rem; }
  .step-label { display: none; }
  .step.current .step-label { display: block; }
  .btn { width: 100%; justify-content: center; margin-right: 0; }
}
</style>
</head>
<body>
<div class='wrap'>
  <header class='top'>
    <div class='brand'><span class='brand-mark' aria-hidden='true'>D</span><span>Duplicator <small>Installer</small></span></div>
    <a class='help' target='_blank' rel='noopener' href='https://processwire.com/talk/topic/15345-duplicator-backup-and-move-sites/'>" . $this->icon('help') . " Need help?</a>
  </header>
  <ol class='steps' aria-label='Installation steps'>$steps</ol>
  <main class='card'>
    <form action='" . htmlentities($formAction, ENT_QUOTES, 'UTF-8') . "' method='post'>
";
  }

  /**
   *
   */
  protected function renderFooter()
  {
    echo "
    </form>
  </main>
  <footer class='foot'>ProcessWire &copy; " . date('Y') . " &middot; Duplicator Installer by <a href='https://processwire.com/talk/profile/4048-flydev/' target='_blank' rel='noopener'>flydev</a></footer>
</div>
</body>
</html>
";
  }


  /**
   * Output a button
   *
   * @param string $label
   * @param string $value
   * @param string $icon
   * @param bool $secondary
   * @param bool $float Kept for compatibility, buttons always line up
   * @param string $href
   *
   */
  protected function btn($label, $value, $icon = 'angle-right', $secondary = false, $float = false, $href = '')
  {
    $class = 'btn' . ($secondary ? ' secondary' : '');
    if ($icon === 'refresh') $class .= ' icon-left';
    if ($href) {
      echo "\n<a class='$class' href='" . htmlentities($href, ENT_QUOTES, 'UTF-8') . "'>$label " . $this->icon($icon) . "</a>";
    } else {
      echo "\n<button name='step' type='submit' class='$class' value='$value'>$label " . $this->icon($icon) . "</button>";
    }
  }

  /**
   * Output a headline; "Step #N - Title" is shown as a small "Step N of 5" above the title
   *
   * @param string $label
   *
   */
  protected function h($label)
  {
    if (preg_match('/^Step #(\d+)\s*-\s*(.+)$/', $label, $m)) {
      echo "\n<p class='eyebrow'>Step {$m[1]} of " . count(self::$stepLabels) . "</p>";
      $label = $m[2];
    }
    echo "\n<h2>$label</h2>";
  }

  /**
   * Output a paragraph
   *
   * @param string $text
   * @param string $class
   *
   */
  protected function p($text, $class = '')
  {
    if ($class) echo "\n<p class='$class'>$text</p>";
    else echo "\n<p>$text</p>";
  }

  /**
   * Output an <input>
   *
   * @param string $name
   * @param string $label
   * @param string $value
   * @param bool $clear Kept for compatibility, fields wrap on their own
   * @param string $type
   * @param bool $required
   *
   */
  protected function input($name, $label, $value, $clear = false, $type = "text", $required = true)
  {
    $class = 'field';
    $required = $required ? "required='required'" : "";
    $pattern = '';
    $note = '';
    if ($type == 'email') {
      $class .= ' wide';
      $required = '';
    } else if ($type == 'name') {
      $type = 'text';
      $pattern = "pattern='[-_a-z0-9]{2,50}' ";
      $note = " <small>(a-z 0-9)</small>";
    }
    $value = htmlentities($value, ENT_QUOTES, "UTF-8");
    echo "\n<label class='$class'><span>$label$note</span><input type='$type' name='$name' value='$value' $required $pattern/></label>";
  }

  /**
   * @param $text
   * @param string $class
   */
  protected function action($text, $class = '')
  {
    echo "\n<div class='msg msg-info $class'>" . $this->icon('arrow') . "<span>$text</span></div>";
  }

  /**
   * @param $text
   * @param string $class
   */
  protected function info($text, $class = '')
  {
    echo "\n<div class='msg msg-info $class'>" . $this->icon('info') . "<span>$text</span></div>";
  }

  /**
   * Report and log an error
   *
   * @param string $str
   * @return bool
   *
   */
  protected function err($str)
  {
    $this->numErrors++;
    echo "\n<div class='msg msg-err' role='alert'>" . $this->icon('error') . "<span>$str</span></div>";
    return false;
  }

  /**
   * Action/warning
   *
   * @param string $str
   * @return bool
   *
   */
  protected function warn($str)
  {
    $this->numErrors++;
    echo "\n<div class='msg msg-warn'>" . $this->icon('warning') . "<span>$str</span></div>";
    return false;
  }

  /**
   * Report success
   *
   * @param string $str
   * @return bool
   *
   */
  protected function ok($str)
  {
    echo "\n<div class='msg msg-ok'>" . $this->icon('check') . "<span>$str</span></div>";
    return true;
  }

  /**
   * @param $path
   * @param bool $showNote
   * @return bool
   */
  protected function mkdir($path, $showNote = true)
  {
    if (self::TEST_MODE) return true;
    if (is_dir($path) || mkdir($path)) {
      chmod($path, octdec($this->chmodDir));
      if ($showNote) $this->ok("Created directory: $path");
      return true;
    } else {
      if ($showNote) $this->err("Error creating directory: $path");
      return false;
    }
  }


  /**
   * Step 1b: Check for ProcessWire compatibility
   *
   */
  public function compatibilityCheck()
  {
    $this->h("Step #1 - Compatibility Check");

    if (version_compare(PHP_VERSION, self::MIN_REQUIRED_PHP_VERSION) >= 0) {
      $this->ok("PHP version " . PHP_VERSION);
    } else {
      $this->err("ProcessWire requires PHP version " . self::MIN_REQUIRED_PHP_VERSION . " or newer. You are running PHP " . PHP_VERSION);
    }

    if (extension_loaded('pdo_mysql')) {
      $this->ok("PDO (mysql) database");
    } else {
      $this->err("PDO (pdo_mysql) is required (for MySQL database)");
    }

    if (self::TEST_MODE) {
      $this->err("Example error message for test mode");
      $this->warn("Example warning message for test mode");
    }

    $this->checkFunction("filter_var", "Filter functions (filter_var)");
    $this->checkFunction("mysqli_connect", "MySQLi (not required by core, but may be required by some 3rd party modules)");
    $this->checkFunction("imagecreatetruecolor", "GD 2.0 or newer");
    $this->checkFunction("json_encode", "JSON support");
    $this->checkFunction("preg_match", "PCRE support");
    $this->checkFunction("ctype_digit", "CTYPE support");
    $this->checkFunction("iconv", "ICONV support");
    $this->checkFunction("session_save_path", "SESSION support");
    $this->checkFunction("hash", "HASH support");
    $this->checkFunction("spl_autoload_register", "SPL support");

    if (function_exists('apache_get_modules')) {
      if (in_array('mod_rewrite', apache_get_modules())) $this->ok("Found Apache module: mod_rewrite");
      else {
        $this->err("Apache mod_rewrite does not appear to be installed and is required by ProcessWire.");
      }
    } else {
      // apache_get_modules doesn't work on a cgi installation.
      // check for environment var set in htaccess file, as submitted by jmarjie.
      $mod_rewrite = getenv('HTTP_MOD_REWRITE') == 'On' || getenv('REDIRECT_HTTP_MOD_REWRITE') == 'On' ? true : false;
      if ($mod_rewrite) {
        $this->ok("Found Apache module (cgi): mod_rewrite");
      } else {
        $this->err("Unable to determine if Apache mod_rewrite (required by ProcessWire) is installed. On some servers, we may not be able to detect it until your .htaccess file is place. Please click the 'check again' button at the bottom of this screen, if you haven't already.");
        $this->makeTempHtaccess();
      }
    }

    if (class_exists('\ZipArchive')) {
      $this->ok("ZipArchive support");
    } else {
      $this->warn("ZipArchive support was not found. This is required by Duplicator to complete installation.");
    }

    if ($this->numErrors) {
      $this->info("One or more errors were found above. We recommend you correct these issues before proceeding or <a href='http://processwire.com/talk/'>contact ProcessWire support</a> if you have questions or think the error is incorrect. But if you want to proceed anyway, click Continue below.");
      $this->btn("Check Again", 0, 'refresh', false, true);
      $this->btn("Continue to Next Step", 1, 'angle-right', true);
    } else {
      $this->btn("Continue to Next Step", 1, 'angle-right', false);
    }
  }

  protected function makeTempHtaccess()
  {
    $txt = "<IfModule mod_rewrite.c>\n
                      RewriteEngine On\n
                      AddDefaultCharset UTF-8\n
                      <IfModule mod_env.c>\n
                        SetEnv HTTP_MOD_REWRITE On\n
                      </IfModule>\n
                    </IfModule>";
    file_put_contents('.htaccess', $txt);
  }


  /**
   * @return bool
   */
  protected function scanDir()
  {
    $ext = self::DUP_PACKAGE_EXTENSION;
    $ext2 = self::DUP_PACKAGE2_EXTENSION;    //ATO:
    $package = false;
    $warn = false;
    $nfiles = 0;

    $this->h('Step #2 - Checking Packages');

    $strictArray = array(
      '.htaccess',
      'index.php',
      'wire',
      'site'
    );
    // remove previously generated .htaccess
    if (file_exists('.htaccess'))
      unlink('.htaccess');

    if ($handle = opendir('.')) {
      while (false !== ($entry = readdir($handle))) {
        if ($entry !== "." && $entry !== "..") {
          if (in_array($entry, $strictArray)) {
            $warn = true;
            $this->warn("ProcessWire structure file detected.");
            continue;
          } elseif ((strrpos($entry, $ext) !== false)
            || (strrpos($entry, $ext2) !== false)
          )    //ATO:
          {
            $pi = pathinfo($entry);
            if ($pi['extension'] === 'zip') $package = $entry;
          }
          $nfiles++;
        }
      }
      closedir($handle);
    }

    if ($nfiles > 2) {
      $warn = true;
      $this->warn("Other files than the installer and the package files were found.");
      $this->info("It is recommended to place the installer and package files into an empty directory.");
    }
    if (!$package) {
      $this->err("Package Not Found.");
      $this->btn("Check Again", 1, 'refresh', false, true);
      return false;
    } else {
      $this->package = $package;
      $_SESSION['package'] = $package;
      $this->ok("Package Found.");
      $warnstr = "Duplicator will extract the package \"<strong>$package</strong>\"";
      $warnstr .= $warn == false ? '.' : ', but we recommend you correct these issues before proceeding.';
      $this->info($warnstr);
      echo '<div style="display: inline-flex">';
      if ($warn) $this->btn("Check Again", 1, 'refresh', false, true);
      $this->btn('Continue to Next Step', 2);
      echo '</div>';
      $this->p("Note: After you click the button above, be patient &hellip; it may take minutes.");
      return true;
    }
  }


  /**
   * @return bool
   */
  protected function extractPackage()
  {
    $isV2 = strrpos($this->package, self::DUP_PACKAGE2_EXTENSION) == FALSE;
    $this->h("Step #3 - Package Extraction");

    $this->package = $_SESSION['package'];
    if (is_null($this->package)) {
      $this->err("Something went wrong in the installer.");    //ATO: Typo
      return false;
    }
    $path = pathinfo(realpath($this->package), PATHINFO_DIRNAME);
    $this->rootPath = $path;
    $_SESSION['rootpath'] = $path;

    //ATO: Always generate temp path
    $tempfolder = $this->rootPath . DIRECTORY_SEPARATOR . self::DUP_TEMP_FOLDER;
    if (!is_dir($tempfolder)) {
      $this->mkdir($tempfolder); // self::DUP_TEMP_FOLDER);
    }

    if (!$isV2) {    //ATO: Unpack into subdirectory only for V1 files
      $path .= '/' . self::DUP_TEMP_FOLDER;
    }

    $zip = new ZipArchive();
    $res = $zip->open($this->package);
    if ($res == true) {
      //$this->action("Extracting \"<i>{$this->package}</i>\" into \"<i>{$path}</i>\"...");
      if (is_writable($path))
        $zip->extractTo($path);
      else {
        $zip->close();
        $this->err("The temp folder $path is not writable. Please adjust the permissions and try again.");
        $this->btn("Check Again", 2, 'refresh', false, true);
        return false;
      }
      if ($zip) $zip->close();
      $this->ok("The package has been extracted.");
    } else {
      $this->err("An error occured! Duplicator couldn't open {$this->package}.");
    }

    if ($isV2) {    //ATO: Unpack into subdirectory only for V1 files
      if ($handle = opendir($this->rootPath)) {
        while (false !== ($entry = readdir($handle))) {
          if (strrpos($entry, self::DUP_SQL_EXTENSION) !== false) {
            $pi = pathinfo($entry);
            if ($pi['extension'] === 'zip') $sqlzip = $entry;
          }
        }
      }
    } else {
      if ($handle = opendir($tempfolder)) {
        while (false !== ($entry = readdir($handle))) {
          if (strrpos($entry, self::DUP_PW_EXTENSION) !== false) {
            $pi = pathinfo($entry);
            if ($pi['extension'] === 'zip') $pwzip = $entry;
          }
          if (strrpos($entry, self::DUP_SQL_EXTENSION) !== false) {
            $pi = pathinfo($entry);
            if ($pi['extension'] === 'zip') $sqlzip = $entry;
          }
        }
      }
      if (!isset($pwzip) || !$pwzip) {
        $this->err("The file containing the ProcessWire structure couldn't be found.");
        $this->btn("Check Again", 2, 'refresh', false, true);
        return false;
      } else {
        $res = $zip->open($path . DIRECTORY_SEPARATOR . $pwzip);
        if ($res == true) {
          //$this->action("Extracting \"<i>{$pwzip}</i>\" into \"<i>{$this->rootPath}</i>\"...");
          $zip->extractTo($this->rootPath);
          $zip->close();
          $this->ok("The ProcessWire structure has been extracted.");
          //$this->btn('Continue to Next Step', 3);
          //return true;
        } else {
          $this->err("An error occured! Duplicator couldn't open {$pwzip}.");
          $this->btn("Check Again", 2, 'refresh', false, true);
          return false;
        }
      }
    }


    if (!isset($sqlzip) || !$sqlzip) {
      $this->err("The file containing the MySQL database couldn't be found.");
      $this->btn("Check Again", 2, 'refresh', false, true);
      return false;
    } else {
      $res = $zip->open($path . DIRECTORY_SEPARATOR . $sqlzip);
      if ($res == true) {
        //$this->action("Extracting \"<i>{$sqlzip}</i>\" into \"<i>{$tempfolder}</i>\"...");
        $zip->extractTo($tempfolder);
        $zip->close();
        $files = scandir($tempfolder);
        foreach ($files as $file) {
          if (strrpos($file, 'sql') !== false) {
            $pi = pathinfo($file);
            if ($pi['extension'] === 'sql') $sqlfile = $file;
          }
        }
        $_SESSION['sqlfile'] = $tempfolder . DIRECTORY_SEPARATOR . $sqlfile;    //ATO: $tempfolder not $path
        $this->ok("The MySQL database has been extracted.");
        $this->btn('Continue to Next Step', 3);
        return true;
      } else {
        $this->err("An error occured! Duplicator couldn't open {$sqlzip}.");
        $this->btn("Check Again", 2, 'refresh', false, true);
        return false;
      }
    }
  }

  /**
   * Step 2: Configure the database and file permission settings
   *
   * @param array $values
   *
   */
  protected function makeDatabase()
  {

    $this->h("Step #4 - Database Setup");
    $this->p("Please specify a MySQL database and user account on your server. <strong>If the database does not exist, we will attempt to create it</strong>. <strong>If the database already exists, we will overwrite it</strong>, the user account should have full read, write and delete permissions on the database.*");
    $this->p("*Recommended permissions are select, insert, update, delete, create, alter, index, drop, create temporary tables, and lock tables.", "detail");

    if (!is_file($_SESSION['sqlfile'])) {
      $this->err("There is no MySQL database file. Please place one there before continuing.");
      return false;
    }

    if (!isset($values['dbName'])) $values['dbName'] = '';
    // @todo: are there PDO equivalents for the ini_get()s below?
    if (!isset($values['dbHost'])) $values['dbHost'] = ini_get("mysqli.default_host");
    if (!isset($values['dbPort'])) $values['dbPort'] = ini_get("mysqli.default_port");
    if (!isset($values['dbUser'])) $values['dbUser'] = ini_get("mysqli.default_user");
    if (!isset($values['dbPass'])) $values['dbPass'] = ini_get("mysqli.default_pw");
    if (!isset($values['dbEngine'])) $values['dbEngine'] = 'MyISAM';

    if (!$values['dbHost']) $values['dbHost'] = 'localhost';
    if (!$values['dbPort']) $values['dbPort'] = 3306;
    if (!isset($values['dbCharset']) || empty($values['dbCharset'])) $values['dbCharset'] = 'utf8';

    foreach ($values as $key => $value) {
      if (strpos($key, 'chmod') === 0) {
        $values[$key] = (int) $value;
      } else if ($key != 'httpHosts') {
        $values[$key] = htmlspecialchars($value, ENT_QUOTES, 'utf-8');
      }
    }

    echo "<div class='fields'>";
    $this->input('dbName', 'DB Name', $values['dbName']);
    $this->input('dbUser', 'DB User', $values['dbUser']);
    $this->input('dbPass', 'DB Pass', $values['dbPass'], false, 'password', false);
    $this->input('dbHost', 'DB Host', $values['dbHost']);
    $this->input('dbPort', 'DB Port', $values['dbPort'], true);

    echo "<label class='field'><span>DB Charset</span>";
    echo "<select name='dbCharset'>";
    echo "<option value='utf8'" . ($values['dbCharset'] != 'utf8mb4' ? " selected" : "") . ">utf8</option>";
    echo "<option value='utf8mb4'" . ($values['dbCharset'] == 'utf8mb4' ? " selected" : "") . ">utf8mb4</option>";
    echo "</select></label>";
    echo "<label class='field'><span>DB Engine</span>";
    echo "<select name='dbEngine'>";
    echo "<option value='MyISAM'" . ($values['dbEngine'] != 'InnoDB' ? " selected" : "") . ">MyISAM</option>";
    echo "<option value='InnoDB'" . ($values['dbEngine'] == 'InnoDB' ? " selected" : "") . ">InnoDB*</option>";
    echo "</select></label>";
    echo "</div>";

    $this->btn("Continue", 4);

    $this->p("Note: Again, after you click the button above, be patient &hellip; it may take a minute.", "detail");
  }


  /**
   * Step 3: Save database configuration, then begin profile import
   *
   */
  protected function dbSaveConfig()
  {
    $this->h("Step #5 - Test Database and Save Configuration");
    $values = array();
    $database = null;

    // db configuration
    $fields = array('dbUser', 'dbName', 'dbPass', 'dbHost', 'dbPort', 'dbEngine', 'dbCharset');

    foreach ($fields as $field) {
      $value = addslashes($_POST[$field]);
      $value = substr($value, 0, 255);
      if (strpos($value, "'") !== false) $value = str_replace("'", "\\" . "'", $value); // allow for single quotes (i.e. dbPass)
      $values[$field] = trim($value);
    }
    $values['dbCharset'] = ($values['dbCharset'] === 'utf8mb4' ? 'utf8mb4' : 'utf8');
    $values['dbEngine'] = ($values['dbEngine'] === 'InnoDB' ? 'InnoDB' : 'MyISAM');
    // if(!ctype_alnum($values['dbCharset'])) $values['dbCharset'] = 'utf8';

    if (!$values['dbUser'] || !$values['dbName'] || !$values['dbPort']) {

      $this->err("Missing database configuration fields");
    } else {
      error_reporting(0);

      $dsn = "mysql:dbname=$values[dbName];host=$values[dbHost];port=$values[dbPort]";
      $driver_options = array(
        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'UTF8'",
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
      );

      try {
        $database = new \PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options);
      } catch (\Exception $e) {

        if ($e->getCode() == 1049) {
          // If schema does not exist, try to create it
          $database = $this->dbCreateDatabase($dsn, $values, $driver_options);
        } else {
          $this->err("Database connection information did not work.");
          $this->err($e->getMessage());
          $this->btn("Check Again", 3, 'refresh', false, true);
        }
      }
    }

    if ($this->numErrors || !$database) {
      $this->dbConfig($values);
      return;
    }


    $this->ok("Database connection successful to " . htmlspecialchars($values['dbName'], ENT_QUOTES, 'UTF-8'));
    $options = array(
      'dbCharset' => strtolower($values['dbCharset']),
      'dbEngine' => $values['dbEngine']
    );

    if ($options['dbEngine'] == 'InnoDB') {
      $query = $database->query("SELECT VERSION()");
      list($dbVersion) = $query->fetch(\PDO::FETCH_NUM);
      if (version_compare($dbVersion, "5.6.4", "<")) {
        $options['dbEngine'] = 'MyISAM';
        $values['dbEngine'] = 'MyISAM';
        $this->err("Your MySQL version is $dbVersion and InnoDB requires 5.6.4 or newer. Engine changed to MyISAM.");
      }
    }

    $ret = $this->importSQL($database, $_SESSION['sqlfile']);

    $this->rootPath = $_SESSION['rootpath'];

    $configphp = $this->rootPath . DIRECTORY_SEPARATOR . 'site' . DIRECTORY_SEPARATOR . 'config.php';
    if (!file_exists($configphp)) {
      $this->err("Couldn't find \"config.php\" in {$configphp}.");
      $this->btn("Check Again", 4, 'refresh', false, true);
    } else {
      $lines = file($configphp, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

      $content = '';
      foreach ($lines as $line) {
        if (strpos($line, '$config->dbHost') === 0) {
          $value = preg_replace("/'[^']*'/", "'{$values['dbHost']}'", $line);
          $content .= $value . "\n";
        } elseif (strpos($line, '$config->dbName') === 0) {
          //preg_match("/'[^']*'/", $line, $output_array);
          $value = preg_replace("/'[^']*'/", "'{$values['dbName']}'", $line);
          $content .= $value . "\n";
        } elseif (strpos($line, '$config->dbUser') === 0) {
          //preg_match("/'[^']*'/", $line, $output_array);
          $value = preg_replace("/'[^']*'/", "'{$values['dbUser']}'", $line);
          $content .= $value . "\n";
        } elseif (strpos($line, '$config->dbPass') === 0) {
          //preg_match("/'[^']*'/", $line, $output_array);
          $value = preg_replace("/'[^']*'/", "'{$values['dbPass']}'", $line);
          $content .= $value . "\n";
        } elseif (strpos($line, '$config->dbPort') === 0) {
          //preg_match("/'[^']*'/", $line, $output_array);
          $value = preg_replace("/'[^']*'/", "'{$values['dbPort']}'", $line);
          $content .= $value . "\n";
        } elseif (strpos($line, '$config->httpHosts') === 0) {
          //preg_match("/'[^']*'/", $line, $output_array);
          $value = preg_replace("/'[^']*'/", "'{$_SERVER['HTTP_HOST']}'", $line);
          $content .= $value . "\n";
        } else {
          $content .= $line . "\n";
        }
      }
      file_put_contents($configphp, $content);
      $this->ok("Config.php updated: $configphp.");
      // bootstrap pw :  get the wire() var in order to get the backend url
      include_once('./index.php');
      $backendUrl = $wire->config->urls->admin;
      $this->btn("Go to Admin Page", 1, 'globe', false, true, $backendUrl);
    }
  }

  function isWinOS()
  {
    return (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
  }


  /**
   * Create database
   *
   * Note: only handles database names that stick to ascii _a-zA-Z0-9.
   * For database names falling outside that set, they should be created
   * ahead of time.
   *
   * Contains contributions from @plauclair PR #950
   *
   * @param string $dsn
   * @param array $values
   * @param array $driver_options
   * @return \PDO|null
   *
   */
  protected function dbCreateDatabase($dsn, $values, $driver_options)
  {

    $dbCharset = preg_replace('/[^a-z0-9]/', '', strtolower(substr($values['dbCharset'], 0, 64)));
    $dbName = preg_replace('/[^_a-zA-Z0-9]/', '', substr($values['dbName'], 0, 64));
    $dbNameTest = str_replace('_', '', $dbName);

    if (
      ctype_alnum($dbNameTest) && $dbName === $values['dbName']
      && ctype_alnum($dbCharset) && $dbCharset === $values['dbCharset']
    ) {

      // valid database name with no changes after sanitization

      try {
        $dsn2 = "mysql:host=$values[dbHost];port=$values[dbPort]";
        $database = new \PDO($dsn2, $values['dbUser'], $values['dbPass'], $driver_options);
        $database->exec("CREATE SCHEMA IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET `$dbCharset`");
        // reconnect
        $database = new \PDO($dsn, $values['dbUser'], $values['dbPass'], $driver_options);
        if ($database) $this->ok("Created database: $dbName");
      } catch (\Exception $e) {
        $this->err("Failed to create database with name $dbName");
        $this->err($e->getMessage());
        $database = null;
      }
    } else {
      $database = null;
      $this->err("Unable to create database with that name. Please create the database with another tool and try again.");
      $this->btn("Check Again", 3, 'refresh', false, true);
    }

    return $database;
  }

  /**
   * Import profile SQL dump
   *
   * @param \PDO $database
   * @param string $file1
   * @param string $file2
   * @param array $options
   *
   */
  protected function importSQL($database, $file1, array $options = array())
  {
    $defaults = array(
      'dbEngine' => 'MyISAM',
      'dbCharset' => 'utf8',
    );
    $options = array_merge($defaults, $options);
    if (self::TEST_MODE) return;
    $restoreOptions = array();
    $replace = array();
    if ($options['dbEngine'] != 'MyISAM') {
      $replace['ENGINE=MyISAM'] = "ENGINE=$options[dbEngine]";
      $this->warn("Engine changed to '$options[dbEngine]', please keep an eye out for issues.");
    }
    if ($options['dbCharset'] != 'utf8') {
      $replace['CHARSET=utf8'] = "CHARSET=$options[dbCharset]";
      if (strtolower($options['dbCharset']) === 'utf8mb4') {
        if (strtolower($options['dbEngine']) === 'innodb') {
          $replace['(255)'] = '(191)';
          $replace['(250)'] = '(191)';
        } else {
          $replace['(255)'] = '(250)'; // max ley length in utf8mb4 is 1000 (250 * 4)
        }
      }
      $this->warn("Character set has been changed to '$options[dbCharset]', please keep an eye out for issues.");
    }
    if (count($replace)) $restoreOptions['findReplaceCreateTable'] = $replace;

    $backup = new WireDatabaseBackup();
    $backup->setDatabase($database);
    if ($backup->restore($file1, $restoreOptions)) {
      $this->ok("Imported database file: $file1");
      return true;
    } else {
      foreach ($backup->errors() as $error) $this->err($error);
      return false;
    }
  }
}

class WireDatabaseBackup
{

  const fileHeader = '--- WireDatabaseBackup';
  const fileFooter = '--- /WireDatabaseBackup';
  /**
   * Options available for the $options argument to backup() method
   *
   * @var array
   *
   */
  protected $backupOptions = array(
    // filename for backup: default is to make a dated filename, but this can also be used (basename only, no path)
    'filename' => '',

    // optional description of this backup
    'description' => '',

    // if specified, export will only include these tables
    'tables' => array(),

    // username to associate with the backup file (string), optional
    'user' => '',

    // exclude creating or inserting into these tables
    'excludeTables' => array(),

    // exclude creating these tables, but still export data (not supported by mysqldump)
    'excludeCreateTables' => array(),

    // exclude exporting data, but still create tables (not supported by mysqldump)
    'excludeExportTables' => array(),

    // SQL conditions for export of individual tables (table => array(SQL conditions))
    // The 'table' portion (index) may also be a full PCRE regexp, must start with '/' to be recognized as regex
    'whereSQL' => array(),

    // max number of seconds allowed for execution
    'maxSeconds' => 1200,

    // use DROP TABLES statements before CREATE TABLE statements?
    'allowDrop' => true,

    // use UPDATE ON DUPLICATE KEY so that INSERT statements can UPDATE when rows already present (all tables)
    'allowUpdate' => false,

    // table names that will use UPDATE ON DUPLICATE KEY (does NOT require allowUpdate=true)
    'allowUpdateTables' => array(),

    // find and replace in row data during backup (not supported by exec/mysql method)
    'findReplace' => array(
      // Example: 'databass' => 'database'
    ),

    // find and replace in create table statements (not supported by exec/mysqldump)
    'findReplaceCreateTable' => array(
      // Example: 'DEFAULT CHARSET=latin1;' => 'DEFAULT CHARSET=utf8;',
    ),

    // additional SQL queries to append at the bottom
    'extraSQL' => array(
      // Example: UPDATE pages SET CREATED=NOW
    ),

    // EXEC MODE IS CURRRENTLY EXPERIMENTAL AND NOT RECOMMEND FOR USE YET
    // if true, we will try to use mysqldump (exec) first. if false, we won't attempt mysqldump.
    'exec' => false,

    // exec command to use for mysqldump (when in use)
    'execCommand' => '[dbPath]mysqldump
            --complete-insert=TRUE
            --add-locks=FALSE
            --disable-keys=FALSE
            --extended-insert=FALSE
            --default-character-set=utf8
            --comments=FALSE
            --compact
            --skip-disable-keys
            --skip-add-locks
            --add-drop-table=TRUE
            --result-file=[dbFile]
            --port=[dbPort]
            -u[dbUser]
            -p[dbPass]
            -h[dbHost]
            [dbName]
            [tables]'
  );
  /**
   * Options available for the $options argument to restore() method
   *
   * @var array
   *
   */
  protected $restoreOptions = array(

    // table names to restore (empty=all)
    'tables' => array(),

    // allow DROP TABLE statements?
    'allowDrop' => true,

    // halt execution when an error occurs?
    'haltOnError' => false,

    // max number of seconds allowed for execution
    'maxSeconds' => 1200,

    // find and replace in row data (not supported by exec/mysql method)
    'findReplace' => array(
      // Example: 'databass' => 'database'
    ),

    // find and replace in create table statements (not supported by exec/mysql)
    'findReplaceCreateTable' => array(
      // Example: 'DEFAULT CHARSET=latin1;' => 'DEFAULT CHARSET=utf8;',
    ),
    // EXEC MODE IS CURRRENTLY EXPERIMENTAL AND NOT RECOMMEND FOR USE YET
    // if true, we will try to use mysql via exec first (faster). if false, we won't attempt that.
    'exec' => false,

    // command to use for mysql exec
    'execCommand' => '[dbPath]mysql
            --port=[dbPort]
            -u[dbUser]
            -p[dbPass]
            -h[dbHost]
            [dbName] < [dbFile]',
  );

  /**
   * @var null|PDO
   *
   */
  protected $database = null;
  /**
   * @var array
   *
   */
  protected $databaseConfig = array(
    'dbUser' => '',
    'dbPass' => '', // optional (if password is blank)
    'dbHost' => '',
    'dbPort' => '',
    'dbName' => '',
    'dbPath' => '', // optional mysql/mysqldump path on file system
    'dbSocket' => '',
    'dbCharset' => 'utf8',
  );
  /**
   * Array of text indicating details about what methods were used (primarily for debugging)
   *
   * @var array
   *
   */
  protected $notes = array();

  /**
   * Array of text error messages
   *
   * @var array
   *
   */
  protected $errors = array();
  /**
   * Database files path
   *
   * @var string|null
   *
   */
  protected $path = null;
  /**
   * Construct
   *
   * You should follow-up the construct call with one or both of the following:
   *
   *  - $backups->setDatabase(PDO|WireDatabasePDO);
   *  - $backups->setDatabaseConfig(array|object);
   *
   * @param string $path Path where database files are stored
   * @throws Exception
   *
   */
  public function __construct($path = '')
  {
    if (strlen($path)) $this->setPath($path);
  }
  /**
   * Set the database configuration information
   *
   * @param array|object $config Containing these properties: dbUser, dbHost, dbPort, dbName,
   *  and optionally: dbPass, dbPath, dbCharset
   * @return $this
   * @throws Exception if missing required config settings
   *
   */
  public function setDatabaseConfig($config)
  {

    foreach ($this->databaseConfig as $key => $_value) {
      if (is_object($config) && isset($config->$key)) $value = $config->$key;
      else if (is_array($config) && isset($config[$key])) $value = $config[$key];
      else $value = '';
      if (empty($value) && !empty($_value)) $value = $_value; // i.e. dbCharset
      if ($key == 'dbPath' && $value) {
        $value = rtrim($value, '/') . '/';
        if (!is_dir($value)) $value = '';
      }
      $this->databaseConfig[$key] = $value;
    }
    $missing = array();
    $optional = array('dbPass', 'dbPath', 'dbSocket', 'dbPort');
    foreach ($this->databaseConfig as $key => $value) {
      if (empty($value) && !in_array($key, $optional)) $missing[] = $key;
    }
    if (count($missing)) {
      throw new Exception("Missing required config for: " . implode(', ', $missing));
    }

    // $charset = $this->databaseConfig['dbCharset'];
    // $this->backupOptions['findReplaceCreateTable']['DEFAULT CHARSET=latin1;'] = "DEFAULT CHARSET=$charset;";
    return $this;
  }
  /**
   * Set the database connection
   *
   * @param PDO|WireDatabasePDO $database
   * @throws PDOException on invalid connection
   *
   */
  public function setDatabase($database)
  {
    $query = $database->prepare('SELECT DATABASE()');
    $query->execute();
    list($dbName) = $query->fetch(PDO::FETCH_NUM);
    if ($dbName) $this->databaseConfig['dbName'] = $dbName;
    $this->database = $database;
  }
  /**
   * Get current database connection, initiating the connection if not yet active
   *
   * @return null|PDO|WireDatabasePDO
   * @throws Exception
   *
   */
  public function getDatabase()
  {

    if ($this->database) return $this->database;

    $config = $this->databaseConfig;
    if (empty($config['dbUser'])) throw new Exception("Please call setDatabaseConfig(config) to supply config information so we can connect.");

    if ($config['dbSocket']) {
      $dsn = "mysql:unix_socket=$config[dbSocket];dbname=$config[dbName];";
    } else {
      $dsn = "mysql:dbname=$config[dbName];host=$config[dbHost]";
      if ($config['dbPort']) $dsn .= ";port=$config[dbPort]";
    }

    $options = array(
      PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '$config[dbCharset]'",
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );

    $database = new PDO($dsn, $config['dbUser'], $config['dbPass'], $options);
    $this->setDatabase($database);
    return $database;
  }
  /**
   * Add an error and return last error
   *
   * @param string $str If omitted, no error is added
   * @return string
   *
   */
  public function error($str = '')
  {
    if (strlen($str)) $this->errors[] = $str; // append error message
    return count($this->errors) ? end($this->errors) : ''; // return last error
  }
  /**
   * Return all error messages that occurred
   *
   * @param bool $reset Specify true to clear out existing errors or omit just to return error messages
   * @return array
   *
   */
  public function errors($reset = false)
  {
    $errors = $this->errors;
    if ($reset) $this->errors = array();
    return $errors;
  }

  /**
   * Record a note
   *
   * @param $key
   * @param $value
   *
   */
  protected function note($key, $value)
  {
    if (!empty($this->notes[$key])) $this->notes[$key] .= ", $value";
    else $this->notes[$key] = $value;
  }
  /**
   * Get all notes
   *
   * @param bool $reset
   * @return array
   *
   */
  public function notes($reset = false)
  {
    $notes = $this->notes;
    if ($reset) $this->notes = array();
    return $notes;
  }
  /**
   * Set path where database files are stored
   *
   * @param string $path
   * @return $this
   * @throws Exception if path has a problem
   *
   */
  public function setPath($path)
  {
    $path = $this->sanitizePath($path);
    if (!is_dir($path)) throw new Exception("Path doesn't exist: $path");
    if (!is_writable($path)) throw new Exception("Path isn't writable: $path");
    $this->path = $path;
    return $this;
  }

  public function getPath()
  {
    return $this->path;
  }

  /**
   * Return array of all backup files
   *
   * To get additional info on any of them, call getFileInfo($basename) method
   *
   * @return array of strings (basenames)
   *
   */
  public function getFiles()
  {
    $dir = new DirectoryIterator($this->path);
    $files = array();
    foreach ($dir as $file) {
      if ($file->isDot() || $file->isDir()) continue;
      $key = $file->getMTime();
      while (isset($files[$key])) $key++;
      $files[$key] = $file->getBasename();
    }
    krsort($files); // sort by date, newest to oldest
    return array_values($files);
  }

  /**
   * Get information about a backup file
   *
   * @param $filename
   * @return array Returns associative array of information on success, empty array on failure
   *
   */
  public function getFileInfo($filename)
  {

    // all possible info (null values become integers when populated)
    $info = array(
      'description' => '',
      'valid' => false,
      'time' => '', // ISO-8601
      'mtime' => null, // timestamp
      'user' => '',
      'size' => null,
      'basename' => '',
      'pathname' => '',
      'dbName' => '',
      'tables' => array(),
      'excludeTables' => array(),
      'excludeCreateTables' => array(),
      'excludeExportTables' => array(),
      'numTables' => null,
      'numCreateTables' => null,
      'numInserts' => null,
      'numSeconds' => null,
    );

    $filename = $this->sanitizeFilename($filename);
    if (!file_exists($filename)) return array();
    $fp = fopen($filename, "r+");
    $line = fgets($fp);
    if (strpos($line, self::fileHeader) === 0 || strpos($line, "# " . self::fileHeader) === 0) {
      $pos = strpos($line, '{');
      if ($pos !== false) {
        $json = substr($line, $pos);
        $info2 = json_decode($json, true);
        if (!$info2) $info2 = array();
        foreach ($info2 as $key => $value) $info[$key] = $value;
      }
    }
    $bytes = strlen(self::fileFooter) + 255; // some extra bytes in case something gets added at the end
    fseek($fp, $bytes * -1, SEEK_END);
    $foot = fread($fp, $bytes);
    $info['valid'] = strpos($foot, self::fileFooter) !== false;
    fclose($fp);

    // footer summary
    $pos = strpos($foot, self::fileFooter) + strlen(self::fileFooter);
    if ($info['valid'] && $pos !== false) {
      $json = substr($foot, $pos);
      $summary = json_decode($json, true);
      if (is_array($summary)) $info = array_merge($info, $summary);
    }

    $info['size'] = filesize($filename);
    $info['mtime'] = filemtime($filename);
    $info['pathname'] = $filename;
    $info['basename'] = basename($filename);

    return $info;
  }
  /**
   * Get array of all table names
   *
   * @param bool $count If true, returns array will be indexed by name and include count of records as value
   * @param bool $cache Allow use of cache?
   * @return array
   *
   */
  public function getAllTables($count = false, $cache = true)
  {
    static $tables = array();
    static $counts = array();
    if ($cache) {
      if ($count && count($counts)) return $counts;
      if (count($tables)) return $tables;
    } else {
      $tables = array();
      $counts = array();
    }
    $query = $this->database->prepare('SHOW TABLES');
    $query->execute();
    /** @noinspection PhpAssignmentInConditionInspection */
    while ($row = $query->fetch(PDO::FETCH_NUM)) $tables[$row[0]] = $row[0];
    $query->closeCursor();
    if ($count) {
      foreach ($tables as $table) {
        $query = $this->database->prepare("SELECT COUNT(*) FROM `$table`");
        $query->execute();
        $row = $query->fetch(PDO::FETCH_NUM);
        $counts[$table] = (int) $row[0];
      }
      $query->closeCursor();
      return $counts;
    } else {
      return $tables;
    }
  }
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  /**
   * Perform a database export/dump
   *
   * @param array $options See $backupOptions
   * @return string Full path and filename of database export file, or false on failure.
   * @throws Exception on fatal error
   *
   */
  public function backup(array $options = array())
  {
    if (!$this->path) throw new Exception("Please call setPath('/backup/files/path/') first");
    $this->errors(true);
    $options = array_merge($this->backupOptions, $options);

    if (empty($options['filename'])) {
      // generate unique filename
      $tail = ((count($options['tables']) || count($options['excludeTables']) || count($options['excludeExportTables'])) ? '-part' : '');
      $n = 0;
      do {
        $options['filename'] = $this->databaseConfig['dbName'] . '_' . date('Y-m-d_H-i-s') . $tail . ($n ? "-$n" : "") . ".sql";
        $n++;
      } while (file_exists($this->path . $options['filename']));
    } else {
      $options['filename'] = basename($options['filename']);
    }

    set_time_limit($options['maxSeconds']);
    $file = false;

    if ($this->supportsExec($options)) {
      $file = $this->backupExec($this->path . $options['filename'], $options);
      $this->note('method', 'exec_mysqldump');
    }

    if (!$file) {
      $file = $this->backupPDO($this->path . $options['filename'], $options);
      $this->note('method', 'pdo');
    }

    $success = false;
    if ($file && file_exists($file)) {
      if (!filesize($file)) {
        unlink($file);
      } else {
        $success = true;
      }
    }

    return $success ? $file : false;
  }

  public function setBackupOptions(array $options)
  {
    $this->backupOptions = array_merge($this->backupOptions, $options);
    return $this;
  }
  /**
   * Start a new backup file, adding our info header to the top
   *
   * @param string $file
   * @param array $options
   * @return bool
   *
   */
  protected function backupStartFile($file, array $options)
  {
    $fp = fopen($file, 'w+');

    if (!$fp) {
      $this->error("Unable to write header to file: $file");
      return false;
    }

    $info = array(
      'time' => date('Y-m-d H:i:s'),
      'user' => $options['user'],
      'dbName' => $this->databaseConfig['dbName'],
      'description' => $options['description'],
      'tables' => $options['tables'],
      'excludeTables' => $options['excludeTables'],
      'excludeCreateTables' => $options['excludeCreateTables'],
      'excludeExportTables' => $options['excludeExportTables'],
    );

    $json = json_encode($info);
    $json = str_replace(array("\r", "\n"), " ", $json);

    fwrite($fp, "# " . self::fileHeader . " $json\n");
    fclose($fp);
    if (function_exists('wireChmod')) wireChmod($file);
    return true;
  }
  /**
   * End a new backup file, adding our footer to the bottom
   *
   * @param string|resource $file
   * @param array $summary
   * @param array $options
   * @return bool
   *
   */
  protected function backupEndFile($file, array $summary = array(), array $options = array())
  {
    $fp = is_resource($file) ? $file : fopen($file, 'a+');

    if (!$fp) {
      $this->error("Unable to write footer to file: $file");
      return false;
    }

    foreach ($options['extraSQL'] as $sql) {
      fwrite($fp, "\n" . rtrim($sql, '; ') . ";\n");
    }

    $footer = "# " . self::fileFooter;
    if (count($summary)) {
      $json = json_encode($summary);
      $json = str_replace(array("\r", "\n"), " ", $json);
      $footer .= " $json";
    }
    fwrite($fp, "\n$footer");
    fclose($fp);
    return true;
  }
  /**
   * Create a mysql dump file using PDO
   *
   * @param string $file Path + filename to create
   * @param array $options
   * @return string|bool Returns the created file on success or false on error
   *
   */
  protected function backupPDO($file, array $options = array())
  {
    $database = $this->getDatabase();
    $options = array_merge($this->backupOptions, $options);
    if (!$this->backupStartFile($file, $options)) return false;
    $startTime = time();
    $fp = fopen($file, "a+");
    $tables = $this->getAllTables();
    $numCreateTables = 0;
    $numTables = 0;
    $numInserts = 0;
    $hasReplace = count($options['findReplace']);
    foreach ($tables as $table) {
      if (in_array($table, $options['excludeTables'])) continue;
      if (count($options['tables']) && !in_array($table, $options['tables'])) continue;
      if (in_array($table, $options['excludeCreateTables'])) {
        $excludeCreate = true;
      } else {
        $excludeCreate = false;
        if ($options['allowDrop']) fwrite($fp, "\nDROP TABLE IF EXISTS `$table`;");
        $query = $database->prepare("SHOW CREATE TABLE `$table`");
        $query->execute();
        $row = $query->fetch(PDO::FETCH_NUM);
        $createTable = $row[1];
        foreach ($options['findReplaceCreateTable'] as $find => $replace) {
          $createTable = str_replace($find, $replace, $createTable);
        }
        $numCreateTables++;
        fwrite($fp, "\n$createTable;\n");
      }

      if (in_array($table, $options['excludeExportTables'])) continue;
      $numTables++;
      $columns = array();
      $query = $database->prepare("SHOW COLUMNS FROM `$table`");
      $query->execute();
      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = $query->fetch(PDO::FETCH_NUM)) $columns[] = $row[0];
      $query->closeCursor();
      $columnsStr = '`' . implode('`, `', $columns) . '`';
      $sql = "SELECT $columnsStr FROM `$table` ";
      $conditions = array();
      foreach ($options['whereSQL'] as $_table => $_conditions) {
        if ($_table === $table || ($_table[0] == '/' && preg_match($_table, $table))) $conditions = array_merge($conditions, $_conditions);
      }
      if (count($conditions)) {
        $sql .= "WHERE ";
        foreach (array_values($conditions) as $n => $condition) {
          if ($n) $sql .= "AND ";
          $sql .= "($condition) ";
        }
      }

      $query = $database->prepare($sql);
      $this->executeQuery($query);
      /** @noinspection PhpAssignmentInConditionInspection */
      while ($row = $query->fetch(PDO::FETCH_NUM)) {
        $numInserts++;
        $out = "\nINSERT INTO `$table` ($columnsStr) VALUES(";
        foreach ($row as $value) {
          if (is_null($value)) {
            $value = 'NULL';
          } else {
            if ($hasReplace) foreach ($options['findReplace'] as $find => $replace) {
              if (strpos($value, $find)) $value = str_replace($find, $replace, $value);
            }
            $value = $database->quote($value);
          }
          $out .= "$value, ";
        }
        $out = rtrim($out, ", ") . ") ";
        if ($options['allowUpdate']) {
          $out .= "ON DUPLICATE KEY UPDATE ";
          foreach ($columns as $c) $out .= "`$c`=VALUES(`$c`), ";
        }
        $out = rtrim($out, ", ") . ";";
        fwrite($fp, $out);
      }
      $query->closeCursor();
      fwrite($fp, "\n");
    }
    $summary = array(
      'numTables' => $numTables,
      'numCreateTables' => $numCreateTables,
      'numInserts' => $numInserts,
      'numSeconds' => time() - $startTime,
    );
    $this->backupEndFile($fp, $summary, $options); // this does the fclose

    return file_exists($file) ? $file : false;
  }
  /**
   * Create a mysql dump file using exec(mysqldump)
   *
   * @param string $file Path + filename to create
   * @param array $options
   * @return string|bool Returns the created file on success or false on error
   *
   * @todo add backupStartFile/backupEndFile support
   *
   */
  protected function backupExec($file, array $options)
  {
    $cmd = $options['execCommand'];
    $cmd = str_replace(array("\n", "\t"), ' ', $cmd);
    $cmd = str_replace('[tables]', implode(' ', $options['tables']), $cmd);

    foreach ($options['excludeTables'] as $table) {
      $cmd .= " --ignore-table=$table";
    }

    if (strpos($cmd, '[dbFile]')) {
      $cmd = str_replace('[dbFile]', $file, $cmd);
    } else {
      $cmd .= " > $file";
    }

    foreach ($this->databaseConfig as $key => $value) {
      $cmd = str_replace("[$key]", $value, $cmd);
    }

    exec($cmd);

    if (file_exists($file)) {
      if (filesize($file) > 0) return $file;
      unlink($file);
    }

    return false;
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////


  /**
   * Import a database SQL file that was created by this class
   *
   * @param string $filename Filename to restore, optionally including path (if no path, then path set to construct is assumed)
   * @param array $options See WireDatabaseBackup::$restoreOptions
   * @return true on success, false on failure. Call the errors() method to retrieve errors.
   * @throws Exception on fatal error
   *
   */
  public function restore($filename, array $options = array())
  {
    $filename = $this->sanitizeFilename($filename);
    if (!file_exists($filename)) throw new Exception("Restore file does not exist: $filename");
    $options = array_merge($this->restoreOptions, $options);
    set_time_limit($options['maxSeconds']);
    $success = false;

    $this->errors(true);
    $this->notes(true);
    if ($this->supportsExec($options)) {
      $this->note('method', 'exec_mysql');
      $success = $this->restoreExec($filename, $options);
      if (!$success) $this->error("Exec mysql failed, attempting PDO...");
    }
    if (!$success) {
      $this->note('method', 'pdo');
      $success = $this->restorePDO($filename, $options);
    }
    return $success;
  }

  public function setRestoreOptions(array $options)
  {
    $this->restoreOptions = array_merge($this->restoreOptions, $options);
    return $this;
  }
  /**
   * Import a database SQL file using PDO
   *
   * @param string $filename Filename to restore (must be SQL file exported by this class)
   * @param array $options See $restoreOptions
   * @return true on success, false on failure. Call the errors() method to retrieve errors.
   *
   */
  protected function restorePDO($filename, array $options = array())
  {
    $fp = fopen($filename, "rb");
    $numInserts = 0;
    $numTables = 0;
    $numQueries = 0;

    $tables = array(); // selective tables to restore, optional
    foreach ($options['tables'] as $table) $tables[$table] = $table;
    if (!count($tables)) $tables = null;
    while (!feof($fp)) {
      $line = trim(fgets($fp));
      if (!$this->restoreUseLine($line)) continue;

      if (preg_match('/^(INSERT|CREATE|DROP)\s+(?:INTO|TABLE IF EXISTS|TABLE IF NOT EXISTS|TABLE)\s+`?([^\s`]+)/i', $line, $matches)) {
        $command = strtoupper($matches[1]);
        $table = $matches[2];
      } else {
        $command = '';
        $table = '';
      }

      if ($command === 'CREATE') {
        if (!$options['allowDrop'] && stripos($line, 'CREATE TABLE IF NOT EXISTS') === false) {
          $line = str_ireplace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $line);
        }
      } else if ($command === 'DROP') {
        if (!$options['allowDrop']) continue;
      } else if ($command === 'INSERT' && $tables) {
        if (!isset($tables[$table])) continue; // skip tables not selected for import
      }

      while (substr($line, -1) != ';' && !feof($fp)) {
        // get the rest of the lines in the query (if multi-line)
        $_line = trim(fgets($fp));
        if ($this->restoreUseLine($_line)) $line .= $_line;
      }

      $replacements = $command === 'CREATE' ? $options['findReplaceCreateTable'] : $options['findReplace'];
      if (count($replacements)) foreach ($replacements as $find => $replace) {
        if (strpos($line, $find) === false) continue;
        $line = str_replace($find, $replace, $line);
      }
      try {
        $this->executeQuery($line, $options);
        if ($command === 'INSERT') $numInserts++;
        if ($command === 'CREATE') $numTables++;
        $numQueries++;
      } catch (Exception $e) {
        $this->error($e->getMessage());
        if ($options['haltOnError']) break;
      }
    }
    fclose($fp);

    $this->note('queries', $numQueries);
    $this->note('inserts', $numInserts);
    $this->note('tables', $numTables);
    if (count($this->errors) > 0) {
      $this->error(count($this->errors) . " queries generated errors ($numQueries queries and $numInserts inserts for $numTables were successful)");
      return false;
    } else {
      return $numQueries;
    }
  }
  /**
   * Import a database SQL file using exec(mysql)
   *
   * @param string $filename Filename to restore (must be SQL file exported by this class)
   * @param array $options See $restoreOptions
   * @return true on success, false on failure. Call the errors() method to retrieve errors.
   *
   */
  protected function restoreExec($filename, array $options = array())
  {
    $cmd = $options['execCommand'];
    $cmd = str_replace(array("\n", "\t"), ' ', $cmd);
    $cmd = str_replace('[dbFile]', $filename, $cmd);
    foreach ($this->databaseConfig as $key => $value) {
      $cmd = str_replace("[$key]", $value, $cmd);
    }
    $o = array();
    $r = 0;
    exec($cmd, $o, $r);
    if ($r > 0) {
      // 0=success, 1=warning, 2=not found
      $this->error("mysql reported error code $r");
      foreach ($o as $e) $this->error($e);
      return false;
    }
    return true;
  }
  /**
   * Returns true or false if a line should be used for restore
   *
   * @param $line
   * @return bool
   *
   */
  protected function restoreUseLine($line)
  {
    if (empty($line) || substr($line, 0, 2) == '--' || substr($line, 0, 1) == '#') return false;
    return true;
  }
  /**
   * Restore from 2 SQL files while resolving table differences (think of it as array_merge for a DB restore)
   *
   * The CREATE TABLE and INSERT statements in filename2 take precedence of those in filename1.
   * INSERT statements from both will be executed, with filename2 INSERTs updating those of filename1.
   * CREATE TABLE statements in filename1 won't be executed if they also exist in filename2.
   *
   * This method assumes both files follow the SQL dump format created by this class.
   *
   * @param string $filename1 Original filename
   * @param string $filename2 Filename that may have statements that will update/override those in filename1
   * @param $options
   *
   */
  public function restoreMerge($filename1, $filename2, $options)
  {

    $options = array_merge($this->restoreOptions, $options);
    $creates1 = $this->findCreateTables($filename1);
    $creates2 = $this->findCreateTables($filename2);
    $creates = array_merge($creates1, $creates2); // CREATE TABLE statements in filename2 override those in filename1

    foreach ($creates as $table => $create) {
      if ($options['allowDrop']) $this->executeQuery("DROP TABLE IF EXISTS `$table`", $options);
      $this->executeQuery($create, $options);
    }

    $inserts = $this->findInserts($filename1);
    foreach ($inserts as $table => $tableInserts) {
      foreach ($tableInserts as $insert) {
        $this->executeQuery($insert, $options);
      }
    }

    // Convert line 1 to line 2:
    // 1. INSERT INTO `field_process` (pages_id, data) VALUES('6', '17');
    // 2. INSERT INTO `field_process` (pages_id, data) VALUES('6', '17') ON DUPLICATE KEY UPDATE pages_id=VALUES(pages_id), data=VALUES(data);

    $inserts = $this->findInserts($filename2);
    foreach ($inserts as $table => $tableInserts) {
      foreach ($tableInserts as $insert) {
        // check if table existed in both dump files, and has no duplicate update statement
        $regex = '/\s+ON\s+DUPLICATE\s+KEY\s+UPDATE\s+[^\'";]+;$/i';
        if (isset($creates1[$table]) && !preg_match($regex, $insert)) {
          // line doesn't already contain an ON DUPLICATE section, so we need to add it
          $pos1 = strpos($insert, '(') + 1;
          $pos2 = strpos($insert, ')') - $pos1;
          $fields = substr($insert, $pos1, $pos2);
          $insert = rtrim($insert, '; ') . " ON DUPLICATE KEY UPDATE ";
          foreach (explode(',', $fields) as $name) {
            $name = trim($name);
            $insert .= "$name=VALUES($name), ";
          }
          $insert = rtrim($insert, ", ") . ";";
        }
        $this->executeQuery($insert, $options);
      }
    }
  }
  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

  /**
   * Returns array of all create table statements, indexed by table name
   *
   * @param string $filename to extract all CREATE TABLE statements from
   * @param string $regex Regex (PCRE) to match for statement to be returned, must stuff table name into first match
   * @param bool $multi Whether there can be multiple matches per table
   * @return array of statements, indexed by table name. If $multi is true, it will be array of arrays.
   * @throws Exception if unable to open specified file
   *
   */
  protected function findStatements($filename, $regex, $multi = true)
  {
    $filename = $this->sanitizeFilename($filename);
    $fp = fopen($filename, 'r');
    if (!$fp) throw new Exception("Unable to open: $filename");
    $statements = array();
    while (!feof($fp)) {
      $line = trim(fgets($fp));
      if (!preg_match($regex, $line, $matches)) continue;
      if (empty($matches[1])) continue;
      $table = $matches[1];
      while (substr($line, -1) != ';' && !feof($fp)) $line .= " " . rtrim(fgets($fp));
      if ($multi) {
        if (!isset($statements[$table])) $statements[$table] = array();
        $statements[$table][] = $line;
      } else {
        $statements[$table] = $line;
      }
    }
    fclose($fp);
    return $statements;
  }
  /**
   * Returns array of all create table statements, indexed by table name
   *
   * @param string $filename to extract all CREATE TABLE statements from
   * @return bool|array of CREATE TABLE statements, associative: indexed by table name
   * @throws Exception if unable to open specified file
   *
   */
  public function findCreateTables($filename)
  {
    $regex = '/^CREATE\s+TABLE\s+`?([^`\s]+)/i';
    return $this->findStatements($filename, $regex, false);
  }
  /**
   * Returns array of all INSERT statements, indexed by table name
   *
   * @param string $filename to extract all CREATE TABLE statements from
   * @return array of arrays of INSERT statements. Base array is associative indexed by table name.
   *  Inside arrays are numerically indexed by order of appearance.
   *
   */
  public function findInserts($filename)
  {
    $regex = '/^INSERT\s+INTO\s+`?([^`\s]+)/i';
    return $this->findStatements($filename, $regex, true);
  }
  /**
   * Execute an SQL query, either a string or PDOStatement
   *
   * @param string $query
   * @param bool|array $options May be boolean (for haltOnError), or array containing the property (i.e. $options array)
   * @return bool Query result
   * @throws Exception if haltOnError, otherwise it populates $this->errors
   *
   */
  protected function executeQuery($query, $options = array())
  {
    $defaults = array(
      'haltOnError' => false
    );
    if (is_bool($options)) {
      $defaults['haltOnError'] = $options;
      $options = array();
    }
    $options = array_merge($defaults, $options);
    $result = false;
    try {
      if (is_string($query)) {
        $result = $this->getDatabase()->exec($query);
      } else if ($query instanceof PDOStatement) {
        $result = $query->execute();
      }
    } catch (Exception $e) {
      if (empty($options['haltOnError'])) {
        $this->error($e->getMessage());
      } else {
        throw $e;
      }
    }
    return $result;
  }
  /**
   * For path: Normalizes slashes and ensures it ends with a slash
   *
   * @param $path
   * @return string
   *
   */
  protected function sanitizePath($path)
  {
    if (DIRECTORY_SEPARATOR != '/') $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
    $path = rtrim($path, '/') . '/'; // ensure it ends with trailing slash
    return $path;
  }
  /**
   * For filename: Normalizes slashes and ensures it starts with a path
   *
   * @param $filename
   * @return string
   * @throws Exception if path has not yet been set
   *
   */
  protected function sanitizeFilename($filename)
  {
    if (DIRECTORY_SEPARATOR != '/') $filename = str_replace(DIRECTORY_SEPARATOR, '/', $filename);
    if (strpos($filename, '/') === false) {
      $filename = $this->path . $filename;
    }
    if (strpos($filename, '/') === false) {
      $path = $this->getPath();
      if (!strlen($path)) throw new Exception("Please supply full path to file, or call setPath('/backup/files/path/') first");
      $filename = $path . $filename;
    }
    return $filename;
  }

  ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
  /**
   * Determine if exec is available for the given command
   *
   * Note that WireDatabaseBackup does not currently use exec() mode so this is here for future use.
   *
   * @param array $options
   * @return bool
   * @throws Exception on unknown exec type
   *
   */
  protected function supportsExec(array $options = array())
  {
    if (!$options['exec']) return false;

    if (empty($this->databaseConfig['dbUser'])) return false; // no db config options provided

    if (preg_match('{^(?:\[dbPath\])?([_a-zA-Z0-9]+)\s}', $options['execCommand'], $matches)) {
      $type = $matches[1];
    } else {
      throw new Exception("Unable to determine command for exec");
    }
    if ($type == 'mysqldump') {
      // these options are not supported by mysqldump via exec
      if (
        !empty($options['excludeCreateTables']) ||
        !empty($options['excludeExportTables']) ||
        !empty($options['findReplace']) ||
        !empty($options['findReplaceCreateTable']) ||
        !empty($options['allowUpdateTables']) ||
        !empty($options['allowUpdate'])
      ) {
        return false;
      }
    } else if ($type == 'mysql') {
      // these options are not supported by mysql via exec
      if (
        !empty($options['tables']) ||
        !empty($options['allowDrop']) ||
        !empty($options['findReplace']) ||
        !empty($options['findReplaceCreateTable'])
      ) {
        return false;
      }
    } else {
      throw new Exception("Unrecognized exec command: $type");
    }

    // first check if exec is available (http://stackoverflow.com/questions/3938120/check-if-exec-is-disabled)
    if (ini_get('safe_mode')) return false;
    $d = ini_get('disable_functions');
    $s = ini_get('suhosin.executor.func.blacklist');
    if ("$d$s") {
      $a = preg_split('/,\s*/', "$d,$s");
      if (in_array('exec', $a)) return false;
    }

    // now check if mysqldump is available
    $o = array();
    $r = 0;
    $path = $this->databaseConfig['dbPath'];
    exec("{$path}$type --version", $o, $r);
    if (!$r && count($o) && stripos($o[0], $type) !== false && stripos($o[0], 'Ver') !== false) {
      // i.e. mysqldump  Ver 10.13 Distrib 5.5.34, for osx10.6 (i386)
      return true;
    }

    return false;
  }
}

// main program
$inst = new Installer();
$inst->execute();
