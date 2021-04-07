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
 * Lints C/C++ source files using flawfinder.
 */
final class FlawfinderLinter extends PinterestExternalLinter {

  public function getInfoName() {
    return 'Flawfinder';
  }

  public function getInfoURI() {
    return 'https://dwheeler.com/flawfinder/';
  }

  public function getInfoDescription() {
    return 'Lexically find potential security flaws in C/C++ source code';
  }

  public function getLinterName() {
    return 'FLAWFINDER';
  }

  public function getLinterConfigurationName() {
    return 'flawfinder';
  }

  public function getDefaultBinary() {
    return 'flawfinder';
  }

  public function getInstallInstructions() {
    return pht('pip install flawfinder');
  }

  protected function getMandatoryFlags() {
    return array('--dataonly', '--columns', '--singleline', '--quiet');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);

    // path/filename.cc:36:27:  [4] (buffer) StrCat:Does not check for buffer overflows when concatenating to destination [MS-banned] (CWE-120).
    $regexp =
        '/^(?:.*?):(?P<line>\d+):(?P<char>\d+):\s+'.
        '\[(?P<level>\d+)\] \((?P<rule>\w+)\) (?P<msg>.*)$/';

    $messages = array();
    foreach ($lines as $line) {
      $matches = null;
      if (!preg_match($regexp, $line, $matches)) {
        continue;
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches['line']);
      $message->setChar($matches['char']);
      $message->setCode($this->getLinterName().$matches['level']);
      $message->setName(pht('Flawfinder %s rule', $matches['rule']));
      $message->setDescription($matches['msg']);
      $message->setSeverity($this->getLintMessageSeverity($matches['level']));

      $messages[] = $message;
    }

     return $messages;
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ADVICE;
  }
}
