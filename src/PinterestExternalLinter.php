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
 * Base class for all external linters
 */
abstract class PinterestExternalLinter extends ArcanistExternalLinter {
  private $customInstallInstructions = null;

  public function willLintPaths(array $paths) {
    $this->checkAdditionalVersions();
    return parent::willLintPaths($paths);
  }

  /**
   * Check additional version requirements for the linter
   * Unlike checkBinaryVersion(), which is run on load,
   *   these checks only run if the linter matches any paths.
   * Use this to prevent imposing a linter's requirements on an
   *   entire repo when only a subset of developers use it.
   */
  protected function checkAdditionalVersions() {
    return;
  }

  public function getInstallInstructions() {
    if ($this->customInstallInstructions) {
      return pht('Install %s using `%s`.', $this->getBinary(), $this->customInstallInstructions);
    }
    return null;
  }

  /**
   * Return a human-readable string describing how to upgrade the linter.
   *
   * @return string Human readable upgrade instructions
   * @task bin
   */
  public function getUpgradeInstructions() {
    return $this->getInstallInstructions();
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'install-instructions' => array(
        'type' => 'optional string',
        'help' => pht(
          'Specify custom instructions that should be used to install %s',
          $this->getBinary()
        ),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'install-instructions':
        $this->customInstallInstructions = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }
}
