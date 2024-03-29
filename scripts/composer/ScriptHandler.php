<?php

/**
 * @file
 * Contains \WordPressProject\composer\ScriptHandler.
 */

namespace WordPressProject\composer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ScriptHandler
{

  protected static function getWordPressRoot($project_root)
  {
    return $project_root .  '/web';
  }

  public static function createRequiredFiles(Event $event)
  {
    $fs = new Filesystem();
    $root = static::getWordPressRoot(getcwd());

    $dirs = [
      'app/plugins',
      'app/themes',
      'cms',
      'private/scripts/quicksilver',
    ];

    // Required for unit testing
    foreach ($dirs as $dir) {
      if (!$fs->exists($root . '/'. $dir)) {
        $fs->mkdir($root . '/'. $dir);
        $fs->touch($root . '/'. $dir . '/.gitkeep');
      }
    }

    // Create the files directory with chmod 0777
    if (!$fs->exists($root . '/wp-content/uploads')) {
      $oldmask = umask(0);
      $fs->mkdir($root . '/wp-content/uploads', 0777);
      umask($oldmask);
      $event->getIO()->write("Create a wp-content/uploads directory with chmod 0777");
    }
  }

  public static function forcePlugins() {
    $pantheonmu = [
      "pantheon-advanced-page-cache",
      "pantheon",
      "pantheon.php"
    ];
    $pantheonplugin = [
      "pantheon-hud",
      "wp-redis"
    ];
    $muplugins = [
      "wp-native-php-sessions"
    ];

    $fs = new Filesystem();
    $root = static::getWordPressRoot(getcwd());
    $mupath = $root . '/app/mu-plugins/';
    $plugpath = $root . '/app/plugins/';

    if (isset($_ENV['PANTHEON_ENVIRONMENT'])) {
      // Pantheon Specific Plugins
      foreach ($pantheonmu as $plugin) {
        if ($fs->exists($plugpath . '/'. $plugin)) {
          $fs->rename($plugpath . '/'. $plugin, $mupath . '/'. $plugin, true);
        }
      }

    } else {
      // Remove Pantheon Specific Plugins
      foreach ($pantheonmu as $plugin) {
        if ($fs->exists($mupath . '/'. $plugin)) {
          $fs->remove($mupath . '/'. $plugin);
        }
        if ($fs->exists($plugpath . '/'. $plugin)) {
          $fs->remove($plugpath . '/'. $plugin);
        }
      }
      foreach ($pantheonplugin as $plugin) {
        if ($fs->exists($mupath . '/'. $plugin)) {
          $fs->remove($mupath . '/'. $plugin);
        }
        if ($fs->exists($plugpath . '/'. $plugin)) {
          $fs->remove($plugpath . '/'. $plugin);
        }
      }
    }

    foreach ($muplugins as $plugin) {
      if ($fs->exists($plugpath . '/'. $plugin)) {
        $fs->rename($plugpath . '/'. $plugin, $mupath . '/'. $plugin, true);
      }
    }
  }

  // This is called by the QuickSilver deploy hook to convert from
  // a 'lean' repository to a 'fat' repository. This should only be
  // called when using this repository as a custom upstream, and
  // updating it with `terminus composer <site>.<env> update`. This
  // is not used in the GitHub PR workflow.
  public static function prepareForPantheon()
  {
    // Get rid of any .git directories that Composer may have added.
    // n.b. Ideally, there are none of these, as removing them may
    // impair Composer's ability to update them later. However, leaving
    // them in place prevents us from pushing to Pantheon.
    $dirsToDelete = [];
    $finder = new Finder();
    foreach (
      $finder
        ->directories()
        ->in(getcwd())
        ->ignoreDotFiles(false)
        ->ignoreVCS(false)
        ->depth('> 0')
        ->name('.git')
      as $dir) {
      $dirsToDelete[] = $dir;
    }
    $fs = new Filesystem();
    $fs->remove($dirsToDelete);

    // Fix up .gitignore: remove everything above the "::: cut :::" line
    $gitignoreFile = getcwd() . '/.gitignore';
    $gitignoreContents = file_get_contents($gitignoreFile);
    $gitignoreContents = preg_replace('/.*::: cut :::*/s', '', $gitignoreContents);
    file_put_contents($gitignoreFile, $gitignoreContents);
  }
}
