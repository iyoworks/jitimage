<?php

/**
 * This File is part of the vendor\thapp\jitimage\src\Thapp\JitImage\Shell package
 *
 * (c) Thomas Appel <mail@thomas-appel.com>
 *
 * For full copyright and license information, please refer to the LICENSE file
 * that was distributed with this package.
 */

namespace Thapp\JitImage\Shell;

use \Closure;

/**
 * Trait: ShellCommand
 *
 * @package
 * @version
 * @author Thomas Appel <mail@thomas-appel.com>
 * @license MIT
 */
trait ShellCommand
{
    /**
     * runCmd
     *
     * @param string  $cmd the shell command
     * @param string  $exception exeption class
     * @param Closure $callback in case of an error call a
     *  callback right before an exception is thrown
     *
     * @access public
     * @throws \RuntimeException;
     * @return string the command result
     */
    public function runCmd($cmd, $exception = '\RuntimeException', Closure $callback = null)
    {
        $cmd = escapeshellcmd($cmd);
        $cmd = preg_replace('~\\\#~', '#', $cmd);
        $exitStatus = $this->execCmd($cmd, $stdout, $stderr);

        if ($exitStatus > 0) {
            if (is_null($callback)) {
                $callback($stderr);
            }
            throw new $exception(sprintf('Command exited with %d: %s', $exitStatus, $stderr));
        }

        return $stdout;
    }

    /**
     * exec
     *
     * @param string $cmd
     * @param string $stdout
     * @param string $stderr
     *
     * @access private
     * @return mixed
     */
    private function execCmd($cmd, &$stdout = null, &$stderr = null)
    {
        $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w")   // stderr is a file to write to
        );

        $pipes= array();
        $process = proc_open($cmd, $descriptorspec, $pipes);

        $stdout = "";
        $stderr = "";

        if (!is_resource($process)) return false;

        #close child's input imidiately
        fclose($pipes[0]);

        stream_set_blocking($pipes[1],false);
        stream_set_blocking($pipes[2],false);

        $todo= array($pipes[1],$pipes[2]);

        while( true ) {
            $readstdout = [];
            $readstderr = [];

            if (false !== !feof($pipes[1])) {
                $readstdout[]= $pipes[1];
            }

            if (false !== !feof($pipes[2])) {
                $readstderr[]= $pipes[2];
            }

            if (empty($readstdout)) {
                break;
            }

            $write = null;
            $ex = null;
            $ready = stream_select($readstdout, $write, $ex, 2);

            if (false === $ready) {
                // probably dead process
                break;
            }

            foreach ($readstdout as $out) {
                $line = fread($out, 1024);
                $stdout.= $line;
            }

            foreach ($readstderr as $out) {
                $line = fread($out, 1024);
                $stderr.= $line;
            }
        }

        $stdout = strlen($stdout) > 0 ? $stdout : null;
        $stderr = strlen($stderr) > 0 ? $stderr : null;

        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process);
    }
}