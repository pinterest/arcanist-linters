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
final class DetectSecretsLinter extends PythonExternalLinter {

  public function getInfoName() {
      return 'detect-secrets'; 
  }

  public function getInfoURI() {
      return 'https://github.com/Yelp/detect-secrets';
  }

  public function getInfoDescription() {
      return 'Detect potential secrets in files to prevent accidental commit';
  }

  public function getLinterName() {
      return 'detect-secrets';
  }

  public function getLinterConfigurationName() {
      return 'detect-secrets';
  }

  public function getPythonBinary() {
	  return 'detect-secrets';
  }

  public function getInstallInstructions() {
      return pht('pip3 install detect-secrets');
  }

  protected function getMandatoryFlags() {
      return array('scan');
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {

      $json = json_decode($stdout, true);
      $json_results = $json["results"];

      $messages = array();

      $error_message = "Looks like you are about to commit secrets to this repo. Please avoid this practice\n\n";
      $error_message .= "Possible mitigations:\n";
      $error_message .= "1. For information about putting your secrets in a safer place, please refer pinch/knox \n";
      $error_message .= "2. If secret has already been committed please rotate that secret. If rotation is taking significant time then please contact #security_related slack channel\n";
      $error_message .= "3. If its a test file with secrets (not belonging to any prod service) mark false positives with an inline `pragma: allowlist secret` comment\n";

      if (count($json_results)) {
          foreach ($json_results as $result) {
              foreach ($result as $output) {
		  $message = new ArcanistLintMessage();
                  $message->setPath($path);
                  $message->setCode($this->getLinterName());
		  $message->setName($this->getLinterName());
		  $message->setLine($output["line_number"]);
		  $message->setDescription($error_message."\n".json_encode($output, true));
		  $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);

		  $messages[] = $message;
	      }
	  }
      }

      return $messages;
  }

}
