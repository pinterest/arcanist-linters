<?php
/**
 * Lints SQL files using Squawk
 */
final class SquawkLinter extends NodeExternalLinter {

  private $config = null;

  public function getInfoName() {
    return 'Squawk';
  }

  public function getInfoURI() {
    return 'https://squawkhq.com/';
  }

  public function getInfoDescription() {
    return 'Lint SQL using Squawk';
  }

  public function getLinterName() {
    return 'SQUAWK';
  }

  public function getLinterConfigurationName() {
    return 'squawk';
  }

  public function getVersion() {
    list($err, $stdout, $stderr) = exec_manual('%C --version', $this->getExecutableCommand());
    return trim($stdout);
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'squawk.config' => array(
        'type' => 'string',
        'help' => pht('Configuration file to use'),
      ),
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'squawk.config':
        $this->config = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function getNodeBinary() {
    return 'squawk';
  }

  public function getNpmPackageName() {
    return 'squawk-cli';
  }

  protected function getMandatoryFlags() {
    return array('-c', $this->config);
  }

  protected function getDefaultFlags() {
    return array('--reporter', 'Json');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  public function getLintSeverityMap() {
    return array(
      'Error' => ArcanistLintSeverity::SEVERITY_ERROR,
      'Warning' => ArcanistLintSeverity::SEVERITY_WARNING
    );
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    /*
      [
      	{
      		"file": "path/to.sql",
      		"line": 0,
      		"column": 0,
      		"level": "Error",
      		"messages": [
      			{
      				"Note": "Postgres failed to parse query: syntax error at or near \"some_random_stuffs\""
      			},
      			{
      				"Help": "Modify your Postgres statement to use valid syntax."
      			}
      		],
      		"rule_name": "invalid-statement"
      	},
      	{
      		"file": "path/to_second.sql",
      		"line": 1,
      		"column": 0,
      		"level": "Warning",
      		"messages": [
      			{
      				"Note": "Dropping a column may break existing clients."
      			}
      		],
      		"rule_name": "ban-drop-column"
      	}
      ]
    */
    $json = json_decode($stdout, true);

    $messages = array();
    foreach ($json as $item) {
      $level = $item['level'];
      $rulename = $item['rule_name'];
      $description = "$level ($rulename): ";

      foreach($item['messages'] as $message) {
        $value = '';

        if (array_key_exists('Note', $message)) {
          $value = $message['Note'];
        }

        if (array_key_exists('Help', $message)) {
          $value = $message['Help'];
        }

        $description.="\n$value";
      }

      $line = $item['line'];
      $column = $item['column'];

      // $line=0 means the file has broken syntax
      // otherwise sum the $line and $ column to get the first row of error
      if ($line == '0') {
        $line = 1;
      } else if ($column != '0') {
        $line = $line + $column - 1;
      }

      $messages[] = id(new ArcanistLintMessage())
        ->setName($this->getLinterName())
        ->setCode($rulename)
        ->setPath(idx($item, 'file', $path))
        ->setLine($line)
        ->setChar(1)
        ->setDescription($description)
        ->setSeverity($this->getLintMessageSeverity($level));
    }

    return $messages;
  }
}
