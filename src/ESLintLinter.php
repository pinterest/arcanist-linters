<?php
/**
 * Copyright 2016 Pinterest, Inc.
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
 * Lints JavaScript and JSX files using ESLint
 */
final class ESLintLinter extends NodeExternalLinter {
  const ESLINT_WARNING = '1';
  const ESLINT_ERROR = '2';

  private $flags = array();

  public function getInfoName() {
    return 'ESLint';
  }

  public function getInfoURI() {
    return 'https://eslint.org/';
  }

  public function getInfoDescription() {
    return pht('The pluggable linting utility for JavaScript and JSX');
  }

  public function getLinterName() {
    return 'ESLINT';
  }

  public function getLinterConfigurationName() {
    return 'eslint';
  }

  public function getNodeBinary() {
    return 'eslint';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C -v', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^v(\d\.\d\.\d)$/', $stdout, $matches)) {
      return $matches[1];
    } else {
      return false;
    }
  }

  protected function getMandatoryFlags() {
    return array(
      '--format=json',
      '--no-color',
    );
  }

  protected function getDefaultFlags() {
    return $this->flags;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'eslint.config' => array(
        'type' => 'optional string',
        'help' => pht('Use configuration from this file or shareable config. (https://eslint.org/docs/user-guide/command-line-interface#-c---config)'),
      ),
      'eslint.env' => array(
        'type' => 'optional string',
        'help' => pht('Specify environments. To specify multiple environments, separate them using commas. (https://eslint.org/docs/user-guide/command-line-interface#--env)'),
      ),
      'eslint.fix' => array(
        'type' => 'optional bool',
        'help' => pht('Specify whether eslint should autofix issues. (https://eslint.org/docs/user-guide/command-line-interface#fixing-problems)'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'eslint.config':
        $this->flags[] = '--config';
        $this->flags[] = $value;
        return;
      case 'eslint.env':
        $this->flags[] = '--env ';
        $this->flags[] = $value;
        return;
      case 'eslint.fix':
        if ($value) {
          $this->flags[] = '--fix ';
        }
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Gate on $stderr b/c $err (exit code) is expected.
    if ($stderr) {
      return false;
    }

    $json = json_decode($stdout, true);
    $messages = array();

    foreach ($json as $file) {
      foreach ($file['messages'] as $offense) {
        // Skip file ignored warning: if a file is ignored by .eslintingore
        // but linted explicitly (by arcanist), a warning will be reported,
        // containing only: `{fatal:false,severity:1,message:...}`.
        if (strpos($offense['message'], "File ignored ") === 0) {
          continue;
        }

        $message = new ArcanistLintMessage();
        $message->setPath($file['filePath']);
        $message->setSeverity($this->mapSeverity($offense['severity']));
        $message->setName(nonempty(idx($offense, 'ruleId'), 'unknown'));
        $message->setDescription($offense['message']);
        $message->setLine($offense['line']);
        $message->setChar($offense['column']);
        $message->setCode($this->getLinterName());
        $messages[] = $message;
      }
    }

    return $messages;
  }

  private function mapSeverity($eslintSeverity) {
    switch($eslintSeverity) {
      case '0':
      case '1':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case '2':
      default:
        return ArcanistLintSeverity::SEVERITY_ERROR;
    }
  }
}
