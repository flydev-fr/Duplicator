<?php

namespace ProcessWire;

/**
 * Decides which files and symbolic links go into a package
 *
 * - Version control, editor and OS files (.git, .cursor, .DS_Store...) are never packaged.
 * - A symbolic link is followed only when its target can be read (open_basedir) and is not
 *   already in the package: a link into the site itself, or back into a followed folder,
 *   would duplicate files or loop forever.
 *
 * No ProcessWire dependency, so it can be tested on its own (tests/FileFilterTest.php).
 */
class DupFileFilter
{
  /**
   * Names never packaged, wherever they are
   */
  const SKIPPED_NAMES = array(
    // version control
    '.git', '.gitattributes', '.gitignore', '.gitmodules', '.github', '.gitlab', '.gitlab-ci.yml',
    '.svn', '.hg', '.hgignore', '.bzr',
    // editors and AI tools
    '.idea', '.vscode', '.cursor', '.claude', '.fleet', '.nova', '.history',
    // operating systems
    '.DS_Store', '.Spotlight-V100', '.Trashes', '._.DS_Store', 'Thumbs.db', 'desktop.ini',
  );

  /**
   * @param string $name A file or folder name, without its path
   * @return bool True if this name is never packaged
   */
  public static function isSkippedName($name)
  {
    return in_array($name, self::SKIPPED_NAMES, true);
  }

  /**
   * is_link() plus the NTFS junctions (mklink /J), for which is_link() returns false
   *
   * @param string $path
   * @return bool
   */
  public static function isLink($path)
  {
    if (is_link($path)) return true;
    if (DIRECTORY_SEPARATOR !== '\\') return false;
    // on Windows, readlink() of a plain entry returns its own real path, of a junction its target
    static $parents = array();
    $dir = dirname($path);
    if (!isset($parents[$dir])) {
      if (count($parents) > 256) $parents = array();
      $parents[$dir] = @realpath($dir);
    }
    $target = @readlink($path);
    if ($target === false || $parents[$dir] === false) return false;
    return !self::same(self::normalizePath($target), self::normalizePath($parents[$dir] . '/' . basename($path)));
  }

  /**
   * Decide whether a symbolic link can be packaged
   *
   * @param string $link Path of the link
   * @param string $siteRoot Real path of the site root
   * @param array $followed Real paths of the linked folders already followed, updated when this one is followed
   * @param string|null $basedir open_basedir value, null for the current one
   * @return array [reason it is skipped or null, real path of its target or null]
   */
  public static function checkLink($link, $siteRoot, array &$followed, $basedir = null)
  {
    $target = @readlink($link);
    if ($target === false || $target === '') return array('unreadable link', null);
    if (!self::isAbsolute($target)) $target = dirname($link) . '/' . $target;
    $target = self::normalizePath($target);

    // checked before touching the target: PHP warns on any access outside open_basedir
    if (!self::openBasedirAllows($target, $basedir)) return array('target outside open_basedir', null);

    $real = @realpath($target);
    if ($real === false) return array('broken link', null);
    $real = self::normalizePath($real);
    // the target may itself be a link that leads outside
    if (!self::openBasedirAllows($real, $basedir)) return array('target outside open_basedir', null);

    if (!is_dir($real)) return array(null, $real);

    $root = self::normalizePath($siteRoot);
    if (self::isInside($real, $root) || self::isInside($root, $real)) {
      return array('target inside the site, already packaged', null);
    }
    foreach ($followed as $dir) {
      if (self::isInside($real, $dir) || self::isInside($dir, $real)) {
        return array('target already packaged through another link', null);
      }
    }
    $followed[] = $real;
    return array(null, $real);
  }

  /**
   * @param string $path A normalized absolute path
   * @param string|null $basedir open_basedir value, null for the current one
   * @return bool True if PHP may access this path
   */
  public static function openBasedirAllows($path, $basedir = null)
  {
    if ($basedir === null) $basedir = (string) ini_get('open_basedir');
    if (trim($basedir) === '') return true;
    foreach (explode(PATH_SEPARATOR, $basedir) as $dir) {
      $dir = trim($dir);
      if ($dir === '') continue;
      if ($dir === '.') $dir = (string) getcwd();
      $dir = self::normalizePath($dir);
      // same rule as PHP: a prefix match, "/var/www" also allows "/var/www2"
      if (self::startsWith($path, $dir)) return true;
    }
    return false;
  }

  /**
   * Resolve "." and ".." without touching the file system, use "/" as separator
   */
  public static function normalizePath($path)
  {
    $path = str_replace('\\', '/', (string) $path);
    $prefix = '';
    if (preg_match('#^([A-Za-z]:)?/#', $path, $m)) {
      $prefix = $m[0];
      $path = substr($path, strlen($prefix));
    } else if (strpos($path, '//') === 0) {
      $prefix = '//';
      $path = substr($path, 2);
    }
    $parts = array();
    foreach (explode('/', $path) as $part) {
      if ($part === '' || $part === '.') continue;
      if ($part === '..') {
        array_pop($parts);
        continue;
      }
      $parts[] = $part;
    }
    return $prefix . implode('/', $parts);
  }

  /**
   * @return bool True if $path is $dir or inside it
   */
  public static function isInside($path, $dir)
  {
    $dir = rtrim($dir, '/');
    return self::same($path, $dir) || self::startsWith($path, $dir . '/');
  }

  protected static function isAbsolute($path)
  {
    return (bool) preg_match('#^([A-Za-z]:)?[/\\\\]#', $path);
  }

  protected static function startsWith($haystack, $needle)
  {
    if (DIRECTORY_SEPARATOR === '\\') return stripos($haystack, $needle) === 0;
    return strpos($haystack, $needle) === 0;
  }

  protected static function same($a, $b)
  {
    return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($a, $b) === 0 : $a === $b;
  }
}
