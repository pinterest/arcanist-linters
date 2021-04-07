<?php
/**
 * Copyright 2021 Pinterest, Inc.
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
 * Pyright is a fast type checker meant for large Python source bases.
 */
final class PyrightLinter extends NodeExternalLinter {

  private $projectDirectory = null;
  private $typeshedPath = null;
  private $venvPath = null;

  public function getInfoURI() {
    return 'https://github.com/microsoft/pyright';
  }

  public function getInfoDescription() {
    return 'Pyright is a fast type checker meant for large Python source bases';
  }

  public function getLinterName() {
    return 'Pyright';
  }

  public function getLinterConfigurationName() {
    return 'pyright';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^pyright (\d\.\d\.\d)$/', $stdout, $matches)) {
      return $matches[1];
    } else {
      return false;
    }
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'pyright.project' => array(
        'type' => 'optional string',
        'help' => pht('Use the configuration file at this location (%s)', 'https://github.com/microsoft/pyright/blob/master/docs/configuration.md'),
      ),
      'pyright.typeshed-path' => array(
        'type' => 'optional string',
        'help' => pht('Use typeshed type stubs at this location'),
      ),
      'pyright.venv-path' => array(
        'type' => 'optional string',
        'help' => pht('Directory that contains virtual environments'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'pyright.project':
        $this->projectDirectory = $value;
        return;
      case 'pyright.typeshed-path':
        $this->typeshedPath = $value;
        return;
      case 'pyright.venv-path':
        $this->venvPath = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getNodeBinary() {
    return 'pyright';
  }

  protected function getMandatoryFlags() {
    return array('--outputjson');
  }

  protected function getDefaultFlags() {
    $flags = array();

    if ($this->projectDirectory) {
      $flags[] = '--project '.$this->projectDirectory;
    }
    if ($this->typeshedPath) {
      $flags[] = '--typeshed-path '.$this->typeshedPath;
    }
    if ($this->venvPath) {
      $flags[] = '--venv-path '.$this->venvPath;
    }

    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $messages = array();

    // https://github.com/microsoft/pyright/blob/e372c6572ffe3d1d005e46383a390ed2b0b4acb8/docs/command-line.md#pyright-exit-codes
    switch($err) {
      case 0: // No errors reported
        break;
      case 1: // One or more errors reported
        /* JSON Structure
        https://github.com/microsoft/pyright/blob/e372c6572ffe3d1d005e46383a390ed2b0b4acb8/docs/command-line.md#json-output
        {
          version: string,
          time: string,
          diagnostics: Diagnostic[],
          summary: {
            filesAnalyzed: number,
            errorCount: number,
            warningCount: number,
            informationCount: number,
            timeInSec: number
          }
        }
        
        Diagnostic:
        {
          file: string,
          severity: 'error' | 'warning' | 'information',
          message: string,
          rule?: string,
          range: {
            start: {
              line: number,
              character: number
            },
            end: {
              line: number,
              character: number
            }
          }
        }
        */

        $json = json_decode($stdout, true);
        $errors = $json['diagnostics'];

        foreach ($errors as $error) {
          $rule = $error['rule'];

          $message = new ArcanistLintMessage();
          $message->setPath($path)
            ->setCode(substr($rule, 0, 128))
            ->setName($this->getLinterName())
            ->setLine($error['range']['start']['line'] + 1)
            ->setChar($error['range']['start']['character'] + 1)
            ->setDescription($error['message'])
            ->setSeverity($this->mapSeverity($error['severity']));
          $messages[] = $message;
        }
        break;
      case 2: // Fatal error occurred with no errors or warnings reported
        throw new Exception(pht("Fatal error while linting %s:\n%s", $path, $stderr));
      case 3: // Config file could not be read or parsed
        throw new Exception(pht("Could not read config file at %s", $this->projectDirectory));
      default:
        break;
    }

    return $messages;
  }

  protected function mapSeverity($severity) {
    switch($severity) {
      case 'error':
        return ArcanistLintSeverity::SEVERITY_ERROR;
      case 'warning':
        return ArcanistLintSeverity::SEVERITY_WARNING;
      case 'information':
        return ArcanistLintSeverity::SEVERITY_ADVICE;
      default:
        return ArcanistLintSeverity::SEVERITY_WARNING;
    }
  }
}
