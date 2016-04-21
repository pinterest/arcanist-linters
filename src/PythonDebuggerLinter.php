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
 * Hunts for stray Python debugger (pdb) statements.
 */
final class PythonDebuggerLinter extends ArcanistLinter {

  const LINT_BREAKPOINT = 1;

  public function getInfoName() {
    return 'Python Debugger Linter';
  }

  public function getInfoDescription() {
    return pht('Hunts for stray Python debugger (pdb) statements.');
  }

  public function getInfoURI() {
    return 'https://docs.python.org/2/library/pdb.html';
  }

  public function getLinterName() {
    return 'PYTHON-DEBUGGER';
  }

  public function getLinterConfigurationName() {
    return 'python-debugger';
  }

  public function getLintNameMap() {
    return array(
      self::LINT_BREAKPOINT => pht('Python debugger breakpoint'),
    );
  }

  protected function canCustomizeLintSeverities() {
    return false;
  }

  public function lintPath($path) {
    $lines = phutil_split_lines($this->getData($path), false);
    $regex = '/\b(i?pdb\.set_trace)\b/';

    foreach ($lines as $lineno => $line) {
      $matches = array();
      if (preg_match($regex, $line, $matches, PREG_OFFSET_CAPTURE)) {
        list($breakpoint, $offset) = $matches[1];
        $this->raiseLintAtLine(
          $lineno + 1,
          $offset + 1,
          self::LINT_BREAKPOINT,
          pht('This line contains a Python debugger breakpoint.'),
          $breakpoint);
      }
    }
  }
}
