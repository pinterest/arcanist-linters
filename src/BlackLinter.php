<?php
/**
 * Copyright 2019 Pinterest, Inc.
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
 * Formats Python files using Black
 */
final class BlackLinter extends ArcanistExternalLinter {

  private $pythonVersion = null;
  private $skipLinting = false;

  public function getInfoName() {
    return 'Black';
  }

  public function getInfoURI() {
    return 'https://black.readthedocs.io/';
  }

  public function getInfoDescription() {
    return pht('Black is an opinionated code formatter for Python');
  }

  public function getLinterName() {
    return 'BLACK';
  }

  public function getLinterConfigurationName() {
    return 'black';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'black.python' => array(
        'type' => 'optional string',
        'help' => pht('Python version requirement.'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'black.python':
        $this->pythonVersion = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getLinterPriority() {
    return 0.01;
  }

  public function getDefaultBinary() {
    return 'black';
  }

  protected function getMandatoryFlags() {
    return array('--quiet', '--check');
  }

  private function checkVersion($version, $compare_to) {
    $operator = '==';

    $matches = null;
    if (preg_match('/^([<>]=?|=)\s*(.*)$/', $compare_to, $matches)) {
      $operator = $matches[1];
      $compare_to = $matches[2];
      if ($operator === '=') {
        $operator = '==';
      }
    }

    return version_compare($version, $compare_to, $operator);
  }

  private function getPythonVersion($cmd) {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $cmd);
    return trim(str_replace('Python', '', $stdout));
  }

  public function getVersion() {
    if (!empty($this->pythonVersion)) {
      $foundVersion = $this->getPythonVersion('python3');
      if (!$this->checkVersion($foundVersion, $this->pythonVersion)) {
        $this->skipLinting = true;
        $message = pht(
          "Skipping %s (requires Python version %s but `python3 --version` returned '%s')",
          $this->getLinterConfigurationName(),
          $this->pythonVersion,
          $foundVersion);
        fwrite(STDERR, phutil_console_format(
          "<bg:yellow>** %s **</bg> %s\n",
          "WARNING",
          $message));
      }
    }

    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());
    return trim(str_replace('black, version', '', $stdout));
  }

  public function getInstallInstructions() {
    return pht('pip3 install black');
  }

  public function willLintPaths(array $paths) {
    if (!$this->skipLinting) {
      return parent::willLintPaths($paths);
    }
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    if ($err == 0) {
      return array();
    }

    // Remove lint-only flags since instructions to user should be to fix lint errors.
    $flags = array_diff($this->getCommandFlags(), array('--check', '--quiet'));

    $message = new ArcanistLintMessage();
    $message->setPath($path);
    $message->setCode($this->getLinterName());
    $message->setName($this->getLinterName());
    $message->setDescription("Please run `black ".join(" ", $flags)." ".$path."`\n");
    $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
    $messages[] = $message;
    return $messages;
  }
}
