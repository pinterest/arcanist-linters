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
 * Lints Thrift IDL files using ThriftCheck.
 */
final class ThriftCheckLinter extends PinterestExternalLinter {

  private $config = null;
  private $includes = array();

  public function getInfoName() {
    return 'ThriftCheck';
  }

  public function getInfoURI() {
    return 'https://github.com/pinterest/thriftcheck';
  }

  public function getInfoDescription() {
    return pht('Lint Thrift IDL files using ThriftCheck');
  }

  public function getLinterName() {
    return 'THRIFTCHECK';
  }

  public function getLinterConfigurationName() {
    return 'thriftcheck';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'thriftcheck.config' => array(
        'type' => 'optional string',
        'help' => pht('Path to the configuration file'),
      ),
      'thriftcheck.includes' => array(
        'type' => 'optional list<string>',
        'help' => pht('List of include path directories'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'thriftcheck.config':
        $this->config = $value;
        return;
      case 'thriftcheck.includes':
        $this->includes = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function getDefaultBinary() {
    return 'thriftcheck';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual(
      '%C --version',
      $this->getExecutableCommand());

    $matches = array();
    if (preg_match('/^thriftcheck (?P<version>.*) \(.*\)$/', $stdout, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }
  
  protected function getMandatoryFlags() {
    $flags = array();
    if ($this->config !== null) {
      array_push($flags, '--config', $this->config);
    }
    foreach ($this->includes as $dir) {
      array_push($flags, '-I', $dir);
    }
    return $flags;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    // file.thrift:3:1: error: unable to find include path for "bar.thrift" (include.path)
    $regexp =
      '/^(?:.*?):(?P<line>\d+):(?P<char>\d+): ?(?P<severity>(error|warning)): '.
      '(?P<msg>.*) \((?P<code>.*)\)$/';

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match($regexp, $line, $matches)) {
        continue;
      }

      $severity = ($matches['severity'] === "warning")
        ? ArcanistLintSeverity::SEVERITY_WARNING
        : ArcanistLintSeverity::SEVERITY_ERROR;

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches['line']);
      $message->setChar($matches['char']);
      $message->setCode($matches['code']);
      $message->setName($this->getLinterName());
      $message->setDescription($matches['msg']);
      $message->setSeverity($severity);

      $messages[] = $message;
    }

    return $messages;
  }
}
