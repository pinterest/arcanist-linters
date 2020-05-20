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
 * Base class for external Python-based linters.
 */
abstract class PythonExternalLinter extends ArcanistExternalLinter {

  private $virtualenvs = array('.venv');

  /**
   * Return the name of the external Python-based linter.
   *
   * If the binary exists within one of the recognized virtualenv binary
   * paths, the relative path to that location will be automatically
   * prepended.
   *
   * Otherwise, it is assumed that the binary exists somewhere in the
   * environment's $PATH.
   */
  abstract public function getPythonBinary();

  public function getLinterConfigurationOptions() {
    $options = array(
      'python.virtualenvs' => array(
        'type' => 'optional list<string>',
        'help' => pht('Python virtualenv paths.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'python.virtualenvs':
        $this->virtualenvs = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  final public function getDefaultBinary() {
    return $this->resolveBinaryPath(
      $this->getPythonBinary(),
      $this->getProjectRoot());
  }

  final protected function resolveBinaryPath($bin, $root) {
    $virtualenv = $this->findVirtualenv($root);

    if (!empty($virtualenv)) {
      $path = $virtualenv.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.$bin;
      if (is_executable($path)) {
        return $path;
      }
    }

    return $bin;
  }

  private function findVirtualenv($root) {
    // If the shell environment has an activated virtualenv, defer to PATH.
    // We could also extract this directory from the environment variable
    // and add it to the front of our our search list below, but that might
    // be more confusing than helpful in practice.
    if (!empty(getenv('VIRTUAL_ENV'))) {
      return null;
    }

    foreach ($this->virtualenvs as $virtualenv) {
      $path = Filesystem::resolvePath($virtualenv, $root);
      if (is_dir($path)) {
        return $path;
      }
    }

    return null;
  }
}
