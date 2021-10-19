<?php

final class YamlLinter extends ArcanistExternalLinter {

  public function getInfoName() {
    return "YamlLint";
  }

  public function getInfoURI() {
    return "none";
  }

  public function getInfoDescription() {
    return pht("Verifies the syntax of yaml config files");
  }

  public function getLinterName() {
    return "YamlLint";
  }

  public function getLinterConfigurationName() {
    return "yamllint";
  }

  public function getDefaultBinary() {
    return "yamllint";
  }

  protected function getMandatoryFlags() {
    $root = $this->getProjectRoot();
    return array("-c", $root."/.yamllint", "-f", "auto");
  }

  public function getInstallInstructions() {
    return pht("run `brew install yamllint` on Mac or `sudo apt-get install yamllint` on devapp");
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function canCustomizeLintSeverities() {
    return true;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $lines = phutil_split_lines($stdout, false);
    $regex = '/^(?P<line>\\d+):(?P<offset>\\d+) +(?P<severity>warning|error) +(?P<message>.*) +\\((?P<name>.*)\\)$/';
    $messages = array();
    foreach ($lines as $line) {
      $line = trim($line);
      $matches = null;
      if (!preg_match($regex, $line, $matches)) {
        continue;
      }
      foreach ($matches as $key => $match) {
        $matches[$key] = trim($match);
      }

      $message = new ArcanistLintMessage();
      $message->setPath($path);
      $message->setLine($matches[1]);
      $message->setName($this->getLinterName());
      $message->setCode($matches[5]);
      $message->setDescription($matches[4]);
      $message->setSeverity($matches[3]);

      $messages[] = $message;
    }

    return $messages;
  }

}
