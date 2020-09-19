<?php
/**
 * Copyright 2020 Pinterest, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Base class for external Node-based linters.
 */
abstract class NodeExternalLinter extends ArcanistExternalLinter {
  private $cwd = '';

  /**
   * Return the name of the external Node-based linter.
   *
   * If the binary exists within one of the recognized node_modules binary
   * paths, the relative path to that location will be automatically
   * prepended.
   *
   * Otherwise, it is assumed that the binary exists somewhere in the
   * environment's $PATH (i.e. is installed globally)
   */
  abstract public function getNodeBinary();

  final public function getNodeCwd() {
    if ($this->cwd) {
      return $this->cwd;
    }
    return $this->getProjectRoot();
  }

  final public function getDefaultBinary() {
    return $this->resolveBinaryPath(
      $this->getNodeBinary(),
      $this->getNodeCwd());
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      $this->getLinterConfigurationName().'.cwd' => array(
        'type' => 'optional string',
        'help' => pht(
            'Specify a project sub-directory for both the local %s install and the sub-directory to lint within.',
            $this->getNodeBinary()
          ),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case $this->getLinterConfigurationName().'.cwd':
        $this->cwd = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getInstallInstructions() {
    return pht(
      "\n\t%s[%s globally] run: `%s`\n\t[%s locally] run either: `%s` OR `%s`",
      $this->cwd ? pht("[%s globally] (required for %s) run: `%s`\n\t",
        'yarn',
        '--cwd',
        'npm install --global yarn@1') : '',
      $this->getNodeBinary(),
      'npm install --global '.$this->getNodeBinary(),
      $this->getNodeBinary(),
      'npm install --save-dev '.$this->getNodeBinary(),
      'yarn add --dev '.$this->getNodeBinary()
    );
  }

  final protected function resolveBinaryPath($bin, $root) {
    // Yarn will tell us where the binary is, try that first
    list($err, $stdout, $stderr) = exec_manual('yarn -s --cwd %s bin %s', $root, $bin);
    if ($stdout) {
      return strtok($stdout, "\n");
    }

    // Ask npm for the location of its bin directory
    list($err, $stdout, $stderr) = exec_manual('npm bin');
    if ($stdout) {
      $path = Filesystem::resolvePath(strtok($stdout, "\n"));
    } else {
      // Assume the path is in the standard location
      $modulesPath = Filesystem::resolvePath('node_modules', $root);
      if (is_dir($modulesPath.DIRECTORY_SEPARATOR.'.bin')) {
        $path = $modulesPath.DIRECTORY_SEPARATOR.'.bin';
      }
    }

    if ($path) {
      $binaryPath = $path.DIRECTORY_SEPARATOR.$bin;
      if (Filesystem::binaryExists($binaryPath)) {
        return $binaryPath;
      }
    }

    // Fall back to global binary in $PATH
    return $bin;
  }
}
