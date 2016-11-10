<?php

/**
 * Simple wrapper class for common filesystem tasks like reading and writing
 * files. When things go wrong, this class throws detailed exceptions with
 * good information about what didn't work.
 *
 * Filesystem will resolve relative paths against PWD from the environment.
 * When Filesystem is unable to complete an operation, it throws a
 * FilesystemException.
 */

//----------------

final class BriskFilesystem {

  public static function isWindows() {
    // We can also use PHP_OS, but that's kind of sketchy because it returns
    // "WINNT" for Windows 7 and "Darwin" for Mac OS X. Practically, testing for
    // DIRECTORY_SEPARATOR is more straightforward.
    return (DIRECTORY_SEPARATOR != '/');
  }

  /**
   * Read a file in a manner similar to file_get_contents(), but throw detailed
   * exceptions on failure.
   *
   * @param  string  File path to read. This file must exist and be readable,
   *                 or an exception will be thrown.
   * @return string  Contents of the specified file.
   *
   * @task   file
   */
  public static function readFile($path) {
    $path = self::resolvePath($path);
    $data = @file_get_contents($path);
    if ($data === false) {
      throw new Exception(pht("Failed to read file '%s'.", $path));
    }

    return $data;
  }

  /**
   * Get the last modified time of a file
   *
   * @param string Path to file
   * @return Time last modified
   *
   * @task file
   */
  public static function getModifiedTime($path) {
    $path = self::resolvePath($path);
    $modified_time = @filemtime($path);

    if ($modified_time === false) {
      throw new Exception(pht('Failed to read modified time for %s.', $path));
    }

    return $modified_time;
  }

  /**
   * Identify the MIME type of a file. This returns only the MIME type (like
   * text/plain), not the encoding (like charset=utf-8).
   *
   * @param string Path to the file to examine.
   * @param string Optional default mime type to return if the file's mime
   *               type can not be identified.
   * @return string File mime type.
   *
   * @task file
   *
   * @phutil-external-symbol function mime_content_type
   * @phutil-external-symbol function finfo_open
   * @phutil-external-symbol function finfo_file
   */
  public static function getMimeType(
    $path,
    $default = 'application/octet-stream') {

    $path = self::resolvePath($path);

    $mime_type = null;

    // Fileinfo is the best approach since it doesn't rely on `file`, but
    // it isn't builtin for older versions of PHP.

    if (function_exists('finfo_open')) {
      $finfo = finfo_open(FILEINFO_MIME);
      if ($finfo) {
        $result = finfo_file($finfo, $path);
        if ($result !== false) {
          $mime_type = $result;
        }
      }
    }

    // If we failed Fileinfo, try `file`. This works well but not all systems
    // have the binary.

    if ($mime_type === null) {
      list($err, $stdout) = exec_manual(
        'file --brief --mime %s',
        $path);
      if (!$err) {
        $mime_type = trim($stdout);
      }
    }

    // If we didn't get anywhere, try the deprecated mime_content_type()
    // function.

    if ($mime_type === null) {
      if (function_exists('mime_content_type')) {
        $result = mime_content_type($path);
        if ($result !== false) {
          $mime_type = $result;
        }
      }
    }

    // If we come back with an encoding, strip it off.
    if (strpos($mime_type, ';') !== false) {
      list($type, $encoding) = explode(';', $mime_type, 2);
      $mime_type = $type;
    }

    if ($mime_type === null) {
      $mime_type = $default;
    }

    return $mime_type;
  }

  /**
   * Checks if a path is specified as an absolute path.
   *
   * @param  string
   * @return bool
   */
  public static function isAbsolutePath($path) {
    if (self::isWindows()) {
      return (bool)preg_match('/^[A-Za-z]+:/', $path);
    } else {
      return !strncmp($path, DIRECTORY_SEPARATOR, 1);
    }
  }

  /**
   * Canonicalize a path by resolving it relative to some directory (by
   * default PWD), following parent symlinks and removing artifacts. If the
   * path is itself a symlink it is left unresolved.
   *
   * @param  string    Path, absolute or relative to PWD.
   * @return string    Canonical, absolute path.
   *
   * @task   path
   */
  public static function resolvePath($path, $relative_to = null) {
    $is_absolute = self::isAbsolutePath($path);

    if (!$is_absolute) {
      if (!$relative_to) {
        $relative_to = getcwd();
      }
      $path = $relative_to.DIRECTORY_SEPARATOR.$path;
    }

    if (is_link($path)) {
      $parent_realpath = realpath(dirname($path));
      if ($parent_realpath !== false) {
        return $parent_realpath.DIRECTORY_SEPARATOR.basename($path);
      }
    }

    $realpath = realpath($path);
    if ($realpath !== false) {
      return $realpath;
    }


    // This won't work if the file doesn't exist or is on an unreadable mount
    // or something crazy like that. Try to resolve a parent so we at least
    // cover the nonexistent file case.
    $parts = explode(DIRECTORY_SEPARATOR, trim($path, DIRECTORY_SEPARATOR));
    while (end($parts) !== false) {
      array_pop($parts);
      if (phutil_is_windows()) {
        $attempt = implode(DIRECTORY_SEPARATOR, $parts);
      } else {
        $attempt = DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $parts);
      }
      $realpath = realpath($attempt);
      if ($realpath !== false) {
        $path = $realpath.substr($path, strlen($attempt));
        break;
      }
    }

    return $path;
  }

}
