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

    // every step needs the installer key, see checkAccess()
    if (!$this->checkAccess()) {
      $this->renderFooter();
      return;
    }

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

      case 5:
        $this->cleanup();
        break;

      default:
        $this->welcome();
        break;
    }
    else $this->welcome();

    $this->renderFooter();
  }


  /**
   * File next to the installer that holds the installer key
   *
   * It is a PHP file that exits at once, so requesting it over HTTP shows nothing:
   * only someone with access to the server files (FTP, file manager) can read the key.
   */
  const ACCESS_KEY_FILE = 'installer-key.php';

  /**
   * First line of the temporary .htaccess written by makeTempHtaccess()
   */
  const TEMP_HTACCESS_MARK = '# Temporary file created by the Duplicator installer';

  /**
   * Only let through someone who can read the installer key from the server files
   *
   * The installer writes a site's config.php and imports a database, so it must not be
   * usable by anyone who reaches it over HTTP while it sits on the server.
   *
   * @return bool True if this session has given the right key
   *
   */
  protected function checkAccess()
  {
    $keyFile = __DIR__ . DIRECTORY_SEPARATOR . self::ACCESS_KEY_FILE;
    $key = $this->readAccessKey($keyFile);
    if ($key === null) {
      $key = bin2hex(random_bytes(16));
      $content = "<?php exit; // Duplicator installer key: $key\n";
      $fp = @fopen($keyFile, 'x');
      if (!$fp || fwrite($fp, $content) !== strlen($content)) {
        if ($fp) fclose($fp);
        $this->err("The installer could not create " . self::ACCESS_KEY_FILE . " next to installer.php. Please make this folder writable and reload the page.");
        return false;
      }
      fclose($fp);
      @chmod($keyFile, 0600);
      // read it back, in case another request created it at the same time
      $key = $this->readAccessKey($keyFile);
      if ($key === null) return false;
    }

    $expected = hash('sha256', $key);
    if (isset($_SESSION['dupInstallerKey']) && is_string($_SESSION['dupInstallerKey']) && hash_equals($expected, $_SESSION['dupInstallerKey'])) {
      return true;
    }

    $this->h("Installer key");
    if (isset($_POST['installerKey'])) {
      if (hash_equals($key, trim((string) $_POST['installerKey']))) {
        session_regenerate_id(true);
        $_SESSION['dupInstallerKey'] = $expected;
        unset($_POST['step']);
        return true;
      }
      $this->err("This key is not correct.");
    }
    $this->p("For your security, the installer needs its key before going further. It was written to the file <strong>" . self::ACCESS_KEY_FILE . "</strong>, next to installer.php. Open this file with FTP or your hosting file manager, and copy the key from it.");
    echo "\n<div class='fields'><label class='field wide'><span>Installer key</span><input type='password' name='installerKey' value='' autocomplete='off' required='required' /></label></div>";
    echo "\n<button type='submit' class='btn'>Unlock " . $this->icon('start') . "</button>";
    return false;
  }

  /**
   * @param string $keyFile
   * @return string|null The key, or null if the file does not exist
   *
   */
  protected function readAccessKey($keyFile)
  {
    if (!is_file($keyFile)) return null;
    if (!preg_match('/installer key: ([0-9a-f]{32})/', (string) file_get_contents($keyFile), $m)) {
      // damaged file: start again with a new key
      @unlink($keyFile);
      return null;
    }
    return $m[1];
  }

  /**
   * Last step: remove the installer, its key, the package and the extracted database dump
   *
   */
  protected function cleanup()
  {
    $this->h("Cleanup");
    $dir = __DIR__;
    $files = array();
    foreach (scandir($dir) as $entry) {
      if (preg_match('/\.(package|package2|sql)\.zip$/', $entry)) $files[] = $dir . DIRECTORY_SEPARATOR . $entry;
    }
    $temp = $dir . DIRECTORY_SEPARATOR . self::DUP_TEMP_FOLDER;
    if (is_dir($temp)) {
      foreach (scandir($temp) as $entry) {
        if ($entry !== '.' && $entry !== '..' && is_file($temp . DIRECTORY_SEPARATOR . $entry)) $files[] = $temp . DIRECTORY_SEPARATOR . $entry;
      }
    }
    $files[] = $dir . DIRECTORY_SEPARATOR . self::ACCESS_KEY_FILE;
    $files[] = __FILE__;
    foreach ($files as $file) {
      if (!file_exists($file)) continue;
      if (@unlink($file)) $this->ok("Removed " . htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8'));
      else $this->err("Could not remove " . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . ", please delete it yourself.");
    }
    if (is_dir($temp) && @rmdir($temp)) $this->ok("Removed " . self::DUP_TEMP_FOLDER);
    unset($_SESSION['dupInstallerKey']);
    $this->info("Done. The installer is no longer on this server.");
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
  /**
   * Duplicator logo (Assets/Duplicator-logo-xl-v2.png), inline so that the installer needs no other file
   */
  const LOGO_DATA_URI = 'data:image/png;base64,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';

  /**
   * ProcessWire logo (wire/modules/AdminTheme/AdminThemeUikit/themes/default/logo.svg)
   */
  const PW_LOGO_SVG = "<svg class='pw-logo' viewBox='0 0 256 256' fill='currentColor' aria-hidden='true' xmlns='http://www.w3.org/2000/svg'> <g> <path d='M234.02346,56.2065308 C226.258185,44.611679 213.340949,31.3634673 200.370381,22.7873303 C173.426584,4.33370224 142.216153,-2.21573572 114.611028,0.642976614 C85.8219124,3.7470262 61.1714319,14.5951995 40.9049183,32.6861551 C22.1317268,49.454423 9.73715371,69.9560838 4.27586162,89.5083961 C-1.24942998,109.060708 -0.513435538,127.162331 1.45988289,140.794549 C3.53986718,154.629436 10.4304818,172.037714 10.4304818,172.037714 C11.8384712,175.376434 13.7904564,176.731123 14.8037821,177.296465 C19.8384108,180.048509 28.105015,177.627137 34.4516337,170.50169 C34.7716313,170.06435 34.9422967,169.45634 34.7716313,168.944332 C33.000978,162.128223 32.3609828,156.997474 31.7316543,153.029411 C30.2916651,145.178619 29.65167,132.026409 30.6116627,119.866214 C31.0916591,113.284776 32.3716494,106.244663 34.6116325,98.8632122 C38.9422665,84.281646 48.0728642,69.0600695 62.3447564,56.4092007 C77.715307,42.7876498 97.4271581,34.3715154 116.16835,32.1954806 C122.738967,31.4061347 135.240206,30.6487893 150.290759,34.3608485 C153.501401,35.1608613 167.282631,38.7555854 182.023853,48.7397449 C192.754438,56.0358614 201.394373,65.0386719 207.346328,73.9454809 C213.404949,82.44695 219.986232,96.783179 221.916885,107.279347 C224.647531,119.226204 224.647531,131.88774 222.706212,144.218603 C220.30623,156.570801 215.975596,168.581659 209.24498,179.152495 C204.605015,187.344626 194.983755,198.171465 183.613174,206.299595 C173.362585,213.510377 161.66134,218.715793 149.650764,221.595839 C143.57081,223.035862 137.469522,223.95321 131.218903,224.15588 C125.661612,224.32655 118.291001,224.15588 113.117706,223.2812 C105.427098,222.054513 103.82711,220.091815 102.067123,217.425106 C102.067123,217.425106 100.840466,215.505075 100.499135,210.36366 C100.616467,163.376243 100.595134,175.920443 100.595134,151.525387 C100.595134,144.63461 100.371136,138.383844 100.435135,132.709086 C100.755133,123.396937 101.54446,116.996835 108.20041,110.063391 C113.01104,104.953976 119.741656,101.87126 127.154934,101.87126 C129.405583,101.87126 137.160191,101.977929 143.97614,107.642019 C151.282751,113.74345 152.509409,122.084916 152.797407,124.314285 C154.461394,137.359827 145.842792,147.077316 142.536151,149.541355 C138.440182,152.613404 134.760209,154.106761 132.274895,154.981442 C126.984268,156.752137 121.170979,157.264145 115.944352,156.922806 C115.144358,156.869472 114.41903,157.392147 114.259031,158.19216 L112.499044,167.322972 C110.781724,174.256417 114.632361,176.795124 116.872345,177.691138 C124.029624,179.899173 130.376243,180.816521 137.896186,180.251179 C149.426765,179.440499 160.797346,174.896427 170.450607,165.893616 C178.663878,158.085492 183.346509,148.453338 184.946497,137.679832 C186.546485,125.722308 184.466501,112.847436 179.015875,101.945928 C173.021254,89.9244028 162.674665,79.8869091 149.042768,74.393488 C135.272206,68.9747348 124.317622,68.7827317 110.195062,72.3881226 L110.035063,72.4414568 C100.861799,75.5988406 93.0111915,79.4922361 84.8405865,87.9297042 C79.2406288,93.7537973 74.6539968,100.804577 71.8593512,108.762037 C69.0860388,116.783498 68.3393778,122.767594 68.2113788,132.069076 C68.0407134,138.959853 68.3713775,145.359955 68.3713775,151.354717 L68.3713775,190.832681 C68.3713775,203.462216 67.9447141,205.648918 68.3713775,212.145022 C68.6060424,216.454424 69.2033713,221.329168 71.091357,226.566585 C73.0326757,232.337344 77.1073116,238.257439 79.9019571,240.988149 C83.8165942,245.158882 88.7978899,248.508269 93.693853,250.588302 C104.904435,255.569715 120.125653,256.359061 132.466893,255.879054 C140.637498,255.569715 148.85077,254.439031 156.904042,252.529667 C173.010587,248.700272 188.477137,241.734828 202.077034,232.070673 C216.658258,221.798509 229.330162,207.782285 236.327442,195.878095 C245.298041,181.733869 251.100664,165.861616 254.119308,149.552022 C256.839287,133.210428 256.711288,116.452827 253.063316,100.356569 C250.183338,85.4229975 242.492729,69.0387358 233.61813,55.8118579 L234.02346,56.2065308 Z'> </path> </g> </svg>";

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
      'trash' => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/>',
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
  --bg: #f7f7f8;
  --surface: #ffffff;
  --text: #1d1f24;
  --muted: #6b7079;
  --faint: #9a9fa8;
  --line: #e6e7ea;
  --accent: #c2185b;
  --accent-text: #ffffff;
  --accent-soft: rgba(194, 24, 91, 0.08);
  --ok: #2f7d4f;
  --warn: #a8640a;
  --err: #b42318;
  --radius: 0; /* square like ProcessWire */
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #111215;
    --surface: #17191d;
    --text: #e8e9ec;
    --muted: #9aa0aa;
    --faint: #6d727b;
    --line: #2a2d33;
    --accent: #ee6aa3;
    --accent-text: #1a0610;
    --accent-soft: rgba(238, 106, 163, 0.12);
    --ok: #6cc28f;
    --warn: #e2a64e;
    --err: #f08a7e;
  }
}
* { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; }
body {
  margin: 0;
  background: var(--bg);
  color: var(--text);
  font: 400 15px/1.6 system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration-color: var(--faint); text-underline-offset: 3px; }
a:hover { color: var(--accent); text-decoration-color: currentColor; }
strong { font-weight: 600; }
code { font: 0.9em ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; padding: 0.05em 0.3em; border: 1px solid var(--line); border-radius: 0; }
.icon { width: 1em; height: 1em; flex: none; vertical-align: -0.125em; }
.wrap { max-width: 680px; margin: 0 auto; padding: 0 16px; }

.top { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; padding: 2.5rem 0 1.75rem; }
.brand { display: flex; flex-direction: column; align-items: flex-start; gap: 0.45rem; }
.brand-logo { display: block; height: 22px; width: auto; }
.brand-sub { display: inline-flex; align-items: center; gap: 0.3rem; font-size: 0.75rem; letter-spacing: 0.02em; color: var(--muted); }
.pw-logo { width: 1em; height: 1em; color: var(--muted); }
.help { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; color: var(--muted); text-decoration: none; }
.help:hover { color: var(--text); }

.steps { display: flex; gap: 6px; list-style: none; margin: 0 0 1.25rem; padding: 0; }
.step { flex: 1; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.7rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--faint); }
.step::before { content: ''; height: 2px; border-radius: 0; background: var(--line); }
.step.done { color: var(--muted); }
.step.done::before { background: var(--text); opacity: 0.35; }
.step.current { color: var(--text); }
.step.current::before { background: var(--accent); }

.card { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 2.25rem 2.5rem; }
.eyebrow { margin: 0 0 0.35rem; font-size: 0.72rem; letter-spacing: 0.06em; text-transform: uppercase; color: var(--faint); }
h2 { margin: 0 0 1.25rem; font-size: 1.3rem; font-weight: 600; line-height: 1.3; letter-spacing: -0.015em; }
p { margin: 0 0 1rem; }
.detail { color: var(--muted); font-size: 0.85rem; }

.msg { display: flex; gap: 0.6rem; align-items: baseline; margin: 0; padding: 0.2rem 0; font-size: 0.92rem; overflow-wrap: anywhere; }
.msg .icon { position: relative; top: 0.12em; }
.msg-ok .icon { color: var(--ok); }
.msg-info { color: var(--muted); }
.msg-info .icon { color: var(--faint); }
.msg-warn, .msg-err { margin: 0.5rem 0; padding: 0.55rem 0 0.55rem 0.85rem; border-left: 2px solid currentColor; }
.msg-warn { color: var(--warn); }
.msg-err { color: var(--err); }
.msg-warn span, .msg-err span { color: var(--text); }
.msg + p, .msg + .fields { margin-top: 1.25rem; }
.btn + p, div:has(> .btn) + p { margin-top: 1rem; color: var(--muted); font-size: 0.82rem; }

.fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(11.5rem, 1fr)); gap: 1rem 1.1rem; margin: 0 0 1.25rem; }
.field { display: flex; flex-direction: column; gap: 0.35rem; min-width: 0; font-size: 0.78rem; font-weight: 500; color: var(--muted); }
.field.wide { grid-column: 1 / -1; }
.field small { font-weight: 400; color: var(--faint); }
input[type=text], input[type=password], input[type=email], input[type=number], select {
  width: 100%;
  max-width: 100%;
  font: inherit;
  font-size: 0.92rem;
  font-weight: 400;
  color: var(--text);
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 0;
  height: 38px;
  padding: 0 0.7rem;
  transition: border-color 0.15s, box-shadow 0.15s;
}
select {
  -webkit-appearance: none; appearance: none; padding-right: 2rem;
  background: var(--surface) url('data:image/svg+xml;utf8,<svg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%239a9fa8%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27><path d=%27M6 9l6 6 6-6%27/></svg>') no-repeat right 0.6rem center / 14px;
}
input:hover, select:hover { border-color: var(--faint); }
input:focus, select:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
.btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }

.btn {
  display: inline-flex; align-items: center; gap: 0.5rem;
  height: 38px; margin: 1.25rem 0.5rem 0 0; padding: 0 1.1rem;
  font: inherit; font-size: 0.88rem; font-weight: 500; text-decoration: none;
  color: var(--accent-text); background: var(--accent);
  border: 1px solid var(--accent); border-radius: 999px; cursor: pointer;
  transition: opacity 0.15s, background-color 0.15s, border-color 0.15s;
}
.btn:hover { color: var(--accent-text); opacity: 0.9; }
.btn.secondary { color: var(--text); background: transparent; border-color: var(--line); }
.btn.secondary:hover { color: var(--text); border-color: var(--faint); opacity: 1; }
.btn .icon { order: 2; opacity: 0.85; }
.btn.icon-left .icon { order: 0; }

.foot { padding: 1.5rem 0 3rem; color: var(--faint); font-size: 0.75rem; }
.foot a { color: inherit; }
.foot .gh { display: inline-flex; align-items: center; gap: 0.3rem; vertical-align: bottom; }
.gh-logo { width: 1em; height: 1em; }
@media (max-width: 560px) {
  .top { padding-top: 1.5rem; }
  .card { padding: 1.5rem 1.25rem; }
  .step { font-size: 0; gap: 0; }
  .step.current { font-size: 0.7rem; gap: 0.5rem; }
  .btn { width: 100%; justify-content: center; margin-right: 0; }
}
</style>
</head>
<body>
<div class='wrap'>
  <header class='top'>
    <div class='brand'><img class='brand-logo' src='" . self::LOGO_DATA_URI . "' alt='Duplicator' width='1117' height='177' /><span class='brand-sub'>Installer for " . self::PW_LOGO_SVG . " ProcessWire</span></div>
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
  <footer class='foot'>ProcessWire &copy; " . date('Y') . " &middot; Duplicator Installer by <a href='https://processwire.com/talk/profile/4048-flydev/' target='_blank' rel='noopener'>flydev</a> &middot; <a class='gh' href='https://github.com/flydev-fr/Duplicator' target='_blank' rel='noopener'><svg class='gh-logo' viewBox='0 0 16 16' fill='currentColor' aria-hidden='true'><path d='M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z'/></svg> GitHub</a></footer>
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
    // never overwrite the .htaccess of an existing site
    if (file_exists('.htaccess')) return;
    file_put_contents('.htaccess', self::TEMP_HTACCESS_MARK . "\n" . $txt);
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
    // remove the .htaccess generated by makeTempHtaccess(), and only that one
    if (is_file('.htaccess') && strpos((string) file_get_contents('.htaccess'), self::TEMP_HTACCESS_MARK) === 0)
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
      if (DupDecrypt::isEncryptedFile($package)) $this->secretKeyInput();
      echo '<div style="display: inline-flex">';
      if ($warn) $this->btn("Check Again", 1, 'refresh', false, true);
      $this->btn('Continue to Next Step', 2);
      echo '</div>';
      $this->p("Note: After you click the button above, be patient &hellip; it may take minutes.");
      return true;
    }
  }


  /**
   * Ask for the secret key of an encrypted package (sent with the next step, never stored)
   *
   */
  protected function secretKeyInput()
  {
    $this->info("This package is encrypted. Paste the secret key (<code>dup-sk1:...</code>) that matches the public key set in Duplicator's settings.");
    if (!DupDecrypt::isAvailable()) {
      $this->err("The PHP sodium extension is required to decrypt this package. Enable <code>extension=sodium</code> in php.ini (bundled with PHP 7.2+).");
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $local = isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'), true);
    if (!$https && !$local) {
      $this->warn("This page is not served over HTTPS: the secret key would be sent unencrypted. Use HTTPS, or decrypt the package on your computer with scripts/dupcrypt.php and upload the decrypted package instead.");
    }
    echo "\n<p style='clear: both;'><label>Secret key<br /><input type='password' name='secretKey' value='' autocomplete='off' style='width: 420px;' /></label></p>";
  }

  /**
   * @return bool
   */
  protected function extractPackage()
  {
    $isV2 = strrpos((string) $this->package, self::DUP_PACKAGE2_EXTENSION) == FALSE; // $this->package is not set yet here, the cast keeps the previous result without a PHP 8 deprecation notice
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

    // an encrypted package is first decrypted into the temp folder
    $packageFile = $this->package;
    $decrypted = null;
    if (DupDecrypt::isEncryptedFile($this->package)) {
      // random name, removed even if the script stops (timeout): the plain package must not stay reachable
      $decrypted = $tempfolder . DIRECTORY_SEPARATOR . 'package-' . bin2hex(random_bytes(12)) . '.zip';
      register_shutdown_function(function () use ($decrypted) {
        if (is_file($decrypted)) @unlink($decrypted);
      });
      try {
        DupDecrypt::decryptFile($this->package, $decrypted, isset($_POST['secretKey']) ? $_POST['secretKey'] : '');
      } catch (Exception $e) {
        $this->err(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        $this->secretKeyInput();
        $this->btn("Try Again", 2, 'refresh', false, true);
        return false;
      }
      $this->ok("The package has been decrypted.");
      $packageFile = $decrypted;
    }

    $zip = new ZipArchive();
    $res = $zip->open($packageFile);
    if ($res == true) {
      //$this->action("Extracting \"<i>{$this->package}</i>\" into \"<i>{$path}</i>\"...");
      if (is_writable($path))
        $zip->extractTo($path);
      else {
        $zip->close();
        if ($decrypted) @unlink($decrypted);
        $this->err("The temp folder $path is not writable. Please adjust the permissions and try again.");
        $this->btn("Check Again", 2, 'refresh', false, true);
        return false;
      }
      if ($zip) $zip->close();
      $this->ok("The package has been extracted.");
    } else {
      $this->err("An error occured! Duplicator couldn't open {$this->package}.");
    }
    if ($decrypted) @unlink($decrypted);

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
  protected function makeDatabase(array $values = array())
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

    // raw values: they are escaped where they are used (configValue() for config.php)
    foreach ($fields as $field) {
      $value = isset($_POST[$field]) && is_string($_POST[$field]) ? substr($_POST[$field], 0, 255) : '';
      $values[$field] = $field === 'dbPass' ? $value : trim($value);
    }
    $values['dbCharset'] = ($values['dbCharset'] === 'utf8mb4' ? 'utf8mb4' : 'utf8');
    $values['dbEngine'] = ($values['dbEngine'] === 'InnoDB' ? 'InnoDB' : 'MyISAM');

    if (!$values['dbUser'] || !$values['dbName'] || !$values['dbPort']) {
      $this->err("Missing database configuration fields");
    } else if (!preg_match('/^[-_a-zA-Z0-9]+$/', $values['dbName'])) {
      $this->err("The database name can only contain a-z, A-Z, 0-9, - and _");
    } else if (!$this->isValidHost($values['dbHost'])) {
      $this->err("The database host must be a host name or an IP address");
    } else if (!ctype_digit($values['dbPort']) || (int) $values['dbPort'] > 65535) {
      $this->err("The database port must be a number");
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
          $this->err(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
          if (strtolower($values['dbHost']) === 'localhost') {
            // with 'localhost', PHP connects through the MySQL Unix socket instead of TCP
            $this->warn("With DB Host 'localhost', PHP uses the MySQL socket. If MySQL only listens on TCP (e.g. MAMP), try '127.0.0.1'.");
          }
        }
      }
    }

    if ($this->numErrors || !$database) {
      $this->makeDatabase($values);
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

      // each setting line is rewritten whole, with values exported as PHP strings (never pasted as is)
      $settings = array(
        'dbHost' => $values['dbHost'],
        'dbName' => $values['dbName'],
        'dbUser' => $values['dbUser'],
        'dbPass' => $values['dbPass'],
        'dbPort' => $values['dbPort'],
      );
      $httpHost = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
      if (!$this->isValidHost($httpHost)) {
        $this->warn("The current host name is not valid, \$config->httpHosts was not changed. Please check it in site/config.php.");
        $httpHost = null;
      }

      $content = '';
      foreach ($lines as $line) {
        $name = preg_match('/^\$config->(\w+)\s*=/', $line, $m) ? $m[1] : '';
        if (isset($settings[$name])) {
          $content .= '$config->' . $name . ' = ' . var_export((string) $settings[$name], true) . ";\n";
        } elseif ($name === 'httpHosts' && $httpHost !== null) {
          $content .= '$config->httpHosts = array(' . var_export($httpHost, true) . ");\n";
        } else {
          $content .= $line . "\n";
        }
      }
      file_put_contents($configphp, $content);
      $this->ok("Config.php updated: " . htmlspecialchars($configphp, ENT_QUOTES, 'UTF-8') . ".");
      // bootstrap pw :  get the wire() var in order to get the backend url
      include_once('./index.php');
      $backendUrl = $wire->config->urls->admin;
      $this->btn("Go to Admin Page", 1, 'globe', false, true, $backendUrl);
      $this->p("When the site works, remove the installer files: anyone who gets the installer key could run it again.");
      $this->btn("Remove installer, package and database dump", 5, 'trash', true);
    }
  }

  /**
   * @param string $host Host name, IPv4 or [IPv6] address, with an optional :port
   * @return bool
   *
   */
  protected function isValidHost($host)
  {
    return is_string($host) && preg_match('/^([a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)*|\[[0-9a-f:.]+\])(:\d{1,5})?$/i', $host) === 1;
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

    $sqlMode = $this->restoreSessionStart();

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
        // commit inserts by batches instead of one by one (much faster with InnoDB)
        if ($command === 'INSERT' && $numInserts % self::restoreCommitEvery === 0) $this->restoreCommit();
      } catch (Exception $e) {
        $this->error($e->getMessage());
        if ($options['haltOnError']) break;
      }
    }
    fclose($fp);

    $this->restoreSessionEnd($sqlMode);

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
   * Number of INSERT statements committed together during a PDO restore
   *
   */
  const restoreCommitEvery = 1000;

  /**
   * Prepare the database session for a restore
   *
   * - Foreign key checks are disabled: a table can reference a table that comes later in the dump.
   * - sql_mode is emptied: a strict mode rejects values that the source server accepted (e.g. '' in an ENUM).
   * - autocommit is disabled: inserts are committed by batches (see restoreCommit).
   *
   * @return string|null The previous sql_mode, to give to restoreSessionEnd()
   *
   */
  protected function restoreSessionStart()
  {
    $database = $this->getDatabase();
    $sqlMode = null;
    try {
      $sqlMode = $database->query("SELECT @@SESSION.sql_mode")->fetchColumn();
    } catch (Exception $e) {
      $this->note('session', "sql_mode not read: " . $e->getMessage());
    }
    $statements = array(
      "SET SESSION sql_mode = ''",
      "SET FOREIGN_KEY_CHECKS = 0",
      "SET autocommit = 0",
    );
    foreach ($statements as $statement) {
      try {
        $database->exec($statement);
      } catch (Exception $e) {
        $this->note('session', "$statement failed: " . $e->getMessage());
      }
    }
    return $sqlMode;
  }

  /**
   * Commit what has been imported so far
   *
   */
  protected function restoreCommit()
  {
    try {
      $this->getDatabase()->exec("COMMIT");
    } catch (Exception $e) {
      $this->error("COMMIT failed: " . $e->getMessage());
    }
  }

  /**
   * Commit the last batch and restore the session settings changed by restoreSessionStart()
   *
   * @param string|null $sqlMode The sql_mode returned by restoreSessionStart()
   *
   */
  protected function restoreSessionEnd($sqlMode)
  {
    $this->restoreCommit();
    $database = $this->getDatabase();
    $statements = array(
      "SET autocommit = 1",
      "SET FOREIGN_KEY_CHECKS = 1",
    );
    if ($sqlMode !== null && $sqlMode !== false) {
      $statements[] = "SET SESSION sql_mode = " . $database->quote($sqlMode);
    }
    foreach ($statements as $statement) {
      try {
        $database->exec($statement);
      } catch (Exception $e) {
        $this->note('session', "$statement failed: " . $e->getMessage());
      }
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

/**
 * Decryption of packages encrypted by Duplicator (Classes/DupCrypto.php)
 *
 * This is a copy of the decryption part of DupCrypto, because the installer runs alone.
 * Keep both in sync: see DupCrypto for the file format.
 */
class DupDecrypt
{
  const MAGIC = "DUPENC\x01\x00";
  const CHUNK_SIZE = 1048576;
  const SECRET_KEY_PREFIX = 'dup-sk1:';

  public static function isAvailable()
  {
    return function_exists('sodium_crypto_secretstream_xchacha20poly1305_init_pull')
      && function_exists('sodium_crypto_box_seal_open');
  }

  public static function isEncryptedFile($filename)
  {
    $fp = @fopen($filename, 'rb');
    if (!$fp) return false;
    $magic = fread($fp, strlen(self::MAGIC));
    fclose($fp);
    return $magic === self::MAGIC;
  }

  /**
   * @param string $source Encrypted package
   * @param string $destination Decrypted ZIP to write (overwritten)
   * @param string $secretKey Secret key (dup-sk1:...)
   * @throws Exception On any failure (wrong key, damaged or truncated file); $destination is then removed
   */
  public static function decryptFile($source, $destination, $secretKey)
  {
    if (!self::isAvailable()) throw new Exception("Decryption needs the PHP sodium extension (bundled with PHP 7.2+, enable it in php.ini)");
    $secretKey = preg_replace('/\s+/', '', (string) $secretKey);
    if (strpos($secretKey, self::SECRET_KEY_PREFIX) !== 0) throw new Exception("This is not a Duplicator secret key (it should start with " . self::SECRET_KEY_PREFIX . ")");
    $secret = base64_decode(substr($secretKey, strlen(self::SECRET_KEY_PREFIX)), true);
    if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_BOX_SECRETKEYBYTES) throw new Exception("The Duplicator secret key is damaged");
    $keypair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secret, sodium_crypto_box_publickey_from_secretkey($secret));

    $in = @fopen($source, 'rb');
    if (!$in) throw new Exception("Could not open $source");
    $out = @fopen($destination, 'wb');
    if (!$out) {
      fclose($in);
      throw new Exception("Could not write $destination");
    }
    $error = null;
    try {
      if (self::read($in, strlen(self::MAGIC)) !== self::MAGIC) throw new Exception("$source is not an encrypted Duplicator package");
      $sealed = self::read($in, SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
      $fileKey = sodium_crypto_box_seal_open($sealed, $keypair);
      if ($fileKey === false) throw new Exception("This secret key cannot decrypt this package (wrong key or damaged file)");
      $header = self::read($in, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
      $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $fileKey);
      do {
        $chunk = self::read($in, self::CHUNK_SIZE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES);
        if ($chunk === '') throw new Exception("The package is truncated");
        $result = sodium_crypto_secretstream_xchacha20poly1305_pull($state, $chunk);
        if ($result === false) throw new Exception("The package is damaged");
        list($plain, $tag) = $result;
        if (fwrite($out, $plain) !== strlen($plain)) throw new Exception("Write error (disk full?)");
      } while ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
      if (self::read($in, 1) !== '') throw new Exception("The package has unexpected data at its end");
    } catch (Exception $e) {
      $error = $e;
    }
    fclose($in);
    if (!fclose($out) && !$error) $error = new Exception("Could not write $destination");
    if ($error) {
      @unlink($destination);
      throw $error;
    }
  }

  protected static function read($fp, $length)
  {
    $data = '';
    while (strlen($data) < $length && !feof($fp)) {
      $part = fread($fp, $length - strlen($data));
      if ($part === false) throw new Exception("Read error");
      $data .= $part;
    }
    return $data;
  }
}

// main program
$inst = new Installer();
$inst->execute();
