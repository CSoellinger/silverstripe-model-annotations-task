<?php

namespace CSoellinger\SilverStripe\ModelAnnotations\View;

use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injectable;

/**
 * Print out task messages for a data class.
 */
class DataClassTaskView
{
    use Injectable;

    /**
     * Data class header output.
     */
    public function renderHeader(string $fqn, string $filename): self
    {
        if (Director::is_cli() === true) {
            echo $fqn . PHP_EOL;
            echo 'File: ' . $filename . PHP_EOL . PHP_EOL;
        } else {
            echo '<div class="info">';
            echo '  <h3 style="margin-bottom: 0;">' . $fqn . '</h3>';
            echo '</div>';
        }

        return $this;
    }

    /**
     * Simple message output.
     */
    public function renderMessage(string $message, string $stateClass = 'success'): self
    {
        if (Director::is_cli() === true) {
            echo $message . PHP_EOL . PHP_EOL;
        } else {
            echo '<div class="build" style="padding-bottom: 0;">';
            echo '  <div class="' . $stateClass . '" style="font-weight: 600;">';
            echo $message;
            echo '  </div>';
            echo '</div>';
        }

        return $this;
    }

    /**
     * Source code output.
     */
    public function renderSource(string $filepath, string $source): self
    {
        if (Director::is_cli() === true) {
            echo $source . PHP_EOL . PHP_EOL;
        } else {
            echo '<div class="info">';
            echo '  <small>' . $filepath . '</small>';
            echo '  <pre><code>' . htmlentities($source) . '</code></pre>';
            echo '</div><div>&nbsp;</div>';
        }

        return $this;
    }

    /**
     * Hr line
     */
    public function renderHr(): self
    {
        if (Director::is_cli() === true) {
            echo '--- --- --- --- --- --- --- ---' . PHP_EOL . PHP_EOL;
        } else {
            echo '<div class="info" style="padding: 0;"><hr /></div>';
        }

        return $this;
    }
}
