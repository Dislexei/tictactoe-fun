<?php
$sdk = new Aws\Sdk([
    'endpoint'   => 'http://localhost:8000',
    'version' => 'latest',
    'region'  => 'eu-central-1'//,
  ]
);

try
{
  $client = $sdk->createDynamoDb();
  echo "Client created successfully"."\n";
}
catch (DynamoDbException $e)
{
  echo "Unable to create client"."\n";
}
?>
