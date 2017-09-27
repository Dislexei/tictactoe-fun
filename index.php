<?php
session_start();

use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

require '/home/alexei/vendor/autoload.php';
include './Includes/createClient.php';
include './ui.php';
include './DynamoHelper.php';
include './gameManager.php';

//used for displaying messages for the user
$ui = new UI('texts.csv');
//creates object for easening DB interactions. params is used for querying the DB
$helper = new DynamoHelper;
date_default_timezone_set('UTC');
$loggedIn = FALSE;
welcome($ui, $helper);

/**
 * [welcome description]
 * @param  [type] $_ui     [description]
 * @param  [type] $_helper [description]
 * @return [type]          [description]
 */
function welcome ($_ui, $_helper)
{
    global $loggedIn;
    echo ($_ui->cls());
    if($loggedIn === FALSE) {
        echo ($_ui->yell("welcome_nouser"));
        $_input = readline();
        switch ($_input) {
            case '1':

            echo ($_ui->cls());
            echo ($_ui->yell("registration"));
            register($_ui, $_helper);
            break;

            case '2':
            echo ($_ui->cls());
            login($_ui, $_helper);
            break;

            case '3':
            exit();
            break;

            default:
            break;
        }
    }else{
        echo ($_ui->yell("welcome_loggedin") . $_SESSION['userName'] . PHP_EOL);
        echo ($_ui->yell("welcome_loggedin2"));
        $_input = readline();
        switch ($_input) {

            case '1':
            echo ($_ui->cls());
            $gameManager = new GameManager;
            $gameManager->joinBattle($_helper);
            break;

            case '2':
            session_reset();
            $loggedIn = FALSE;
            welcome($_ui, $_helper);
            break;

            case '3':
            exit();
            break;

            default:
            break;
        }
    }
}

/**
 * Prompts user for username and password, saves username in session variable if correct.
 * @param  [UI object]           $_ui      Helps with outputting messages to the user.
 * @param  [DynamoHelper object] $_helper  Helps with querying the DB
 * @return Nothing
 */
function login($_ui, $_helper)
{
    global $loggedIn;
    echo ($_ui->yell("login"));
    $userName = readline();
    $params = array();
    $params = $_helper->paramsAdd($params, 'login', 'S', $userName);
    $result = $_helper->getItem($params, 'users');

    if(!$_helper->itemExists($result)){
        echo ($_ui->yell("login_err1"));
        login($_ui, $_helper);
    }

    echo ($_ui->yell('login_prompt_pass'));
    $inputPass = readline();
    $salt = $_helper->getSalt();
    $pass = md5($inputPass.$salt);
    unset($params);
    $params = array();
    $params = $_helper->paramsAdd($params, 'login', 'S', $userName);

    $user = $_helper->getItem($params, 'users');
    if($user['Item']['pass']['S'] === $pass)
    {
        $_SESSION['userName'] = $user['Item']['login']['S'];
        $loggedIn = TRUE;
        welcome($_ui, $_helper);
    }
    else
    {
        echo ($_ui->yell('login_err2'));
    }
}

/**
 * Prompts user for username and password, creates a 'user' database entry if correct.
 * @param  [UI object]           $_ui      Helps with outputting messages to the user.
 * @param  [DynamoHelper object] $_helper  Helps with querying the DB
 * @return Nothing
 */
function register($_ui, $_helper)
{
    global $client;
    $userName;
    $userPass;
    $input = readline();
    $params = array();
    $params = $_helper->paramsAdd($params, 'login', 'S', $input);
    $result = $_helper->getItem($params, 'users');

    /**
     * if user exists then throw error and retry. proceed to create user if the name is not taken.
     */
    if(!$_helper->itemExists($result))
    {
        $userName = $input;
        echo ($userName . $_ui->yell("registration_pass"));

    }
    else
    {
        echo ($_ui->yell("registration_err1"));
        register($_ui, $_helper);
    }
    //salts the pass
    $salt = $_helper->getSalt();
    //echo $salt;
    $_pass = readline();
    $pass = md5($_pass.$salt);
    //creates new user
    unset($params);
    $params = array();
    $params = $_helper->paramsAdd($params, 'login', 'S', $userName);
    $params = $_helper->paramsAdd($params, 'pass', 'S', $pass);
    $result = $_helper->putItem($params, 'users');
    echo ($_ui->yell('registration_success'));
    welcome($_ui, $_helper);
}

?>
