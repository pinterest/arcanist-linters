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
 * Detect secrets in code base. 
 */
final class DetectSecretsLinter extends ArcanistExternalLinter {

  public function getInfoName() {
      return 'detect-secrets'; 
  }

  public function getInfoURI() {
      return 'https://github.com/Yelp/detect-secrets';
  }

  public function getInfoDescription() {
      return 'Detect secrets within code base during compile time';
  }

  public function getLinterName() {
      return 'detect-secrets';
  }

  public function getLinterConfigurationName() {
      return 'detect-secrets';
  }

  public function getDefaultBinary() {
	  return 'detect-secrets';
  }

  public function getInstallInstructions() {
      return pht('pip3 install detect-secrets');
  }

  protected function getMandatoryFlags() {
      return array('-v', 'scan');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

      $lines = phutil_split_lines($stdout, false);

      $messages = array();
      $linter_results = array();

      foreach($lines as $line) {
         if ($line !== '') {
            $linter_results[] = $line;
         }
      }

      $results_keyword = "results";
      $version_keyword = "version";
      foreach($linter_results as $result) {
         if (preg_match("/\b$results_keyword\b/i", $result)) {
             $initial = array_search($result, $linter_results);
         }

         if (preg_match("/\b$version_keyword\b/i", $result)) {
             $final = array_search($result, $linter_results);
         }
      }

      $output_string = array();
      for($i = $initial+1; $i < $final; $i += 1) {
         $output_string[] = $linter_results[$i];    
      }

      $error_message = "Looks like you are about to commit secrets to this repo. Please avoid this practice\n\n";
      $error_message .= "Possible mitigations:\n";
      $error_message .= "1. For information about putting your secrets in a safer place, please refer pinch/knox \n";
      $error_message .= "2. If secret has already been committed please rotate that secret. If rotation is taking significant time then please contact #security_related slack channel\n";
      $error_message .= "3. If its a test file with secrets (not belonging to any prod service) mark false positives with an inline `pragma: allowlist secret` comment\n";

      if (!empty($output_string)) {
          $message = new ArcanistLintMessage();
          $message->setPath($path);
          $message->setCode($this->getLinterName());
          $message->setName($this->getLinterName());
          $message->setDescription($error_message."\n".implode("\n", $output_string));
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

          $messages[] = $message;
      }

      return $messages;
  }

}
