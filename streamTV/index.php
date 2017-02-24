<?php


/*EDITED BY TIM TIERNEY FOR STREAMTV*/
/****************************************************************************

streamTV PhP / MySQL / Silex Demonstration

This program is designed to demonstrate how to use PhP, MySQL and Silex to 
implement a web application that accesses a database.

Files:  The application is made up of the following files

php: 	index.php - This file has all of the php code in one place.  It is found in 
		the public_html/toystore/ directory of the code source.
		
		connect.php - This file contains the specific information for connecting to the
		database.  It is stored two levels above the index.php file to prevent the db 
		password from being viewable.
		
twig:	The twig files are used to set up templates for the html pages in the application.
		There are 7 twig files:
		- home.twig - home page for the web site
		- footer.twig - common footer for each of he html files
		- header.twig - common header for each of the html files
		- form.html.twig - template for forms html files (login and register)
		- shows.html.twig - template for show information to be displayed
		- watched.html.twig - template for displaying watched made by a customer
        - shows.html.twig - display show info
        - showEpisodes.html.twig - display all episodes of show
        - episodes.html.twig - display episode info
        - actor.html.twig - display infrmation about actor
		- search.html.twig - template for search results
		
		The twig files are found in the public_html/streamTV/views directory of the source code
		
Silex Files:  Composer was used to compose the needed Service Providers from the Silex 
		Framework.  The code created by composer is found in the vendor directory of the
		source code.  This folder should be stored in a directory called toystore--NOW ITS FOR STREAMTV  that is 
		at the root level of the application.  This code is used by this application and 
		has not been modified.


*****************************************************************************/

// Set time zone  
date_default_timezone_set('America/New_York');

/****************************************************************************   
Silex Setup:
The following code is necessary for one time setup for Silex 
It uses the appropriate services from Silex and Symfony and it
registers the services to the application.
*****************************************************************************/
// Objects we use directly
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Silex\Provider\FormServiceProvider;

// Pull in the Silex code stored in the vendor directory
require_once __DIR__.'/../../silex-files/vendor/autoload.php';

// Create the main application object
$app = new Silex\Application();

// For development, show exceptions in browser
$app['debug'] = true;

// For logging support
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/development.log',
));

// Register validation handler for forms
$app->register(new Silex\Provider\ValidatorServiceProvider());

// Register form handler
$app->register(new FormServiceProvider());

// Register the session service provider for session handling
$app->register(new Silex\Provider\SessionServiceProvider());

// We don't have any translations for our forms, so avoid errors
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
        'translator.messages' => array(),
    ));

// Register the TwigServiceProvider to allow for templating HTML
$app->register(new Silex\Provider\TwigServiceProvider(), array(
        'twig.path' => __DIR__.'/views',
    ));

// Change the default layout 
// Requires including boostrap.css
$app['twig.form.templates'] = array('bootstrap_3_layout.html.twig');

/*************************************************************************
 Database Connection and Queries:
 The following code creates a function that is used throughout the program
 to query the MySQL database.  This section of code also includes the connection
 to the database.  This connection only has to be done once, and the $db object
 is used by the other code.

*****************************************************************************/
// Function for making queries.  The function requires the database connection
// object, the query string with parameters, and the array of parameters to bind
// in the query.  The function uses PDO prepared query statements.

function queryDB($db, $query, $params) {
    // Silex will catch the exception
    $stmt = $db->prepare($query);
    $results = $stmt->execute($params);
    $selectpos = stripos($query, "select");
    if (($selectpos !== false) && ($selectpos < 6)) {
        $results = $stmt->fetchAll();
    }
    return $results;
}



// Connect to the Database at startup, and let Silex catch errors
$app->before(function () use ($app) {
    include '../../connect.php';
    $app['db'] = $db;
});

/*************************************************************************
 Application Code:
 The following code implements the various functionalities of the application, usually
 through different pages.  Each section uses the Silex $app to set up the variables,
 database queries and forms.  Then it renders the pages using twig.

*****************************************************************************/

// Login Page

$app->match('/login', function (Request $request) use ($app) {
	// Use Silex app to create a form with the specified parameters - username and password
	// Form validation is automatically handled using the constraints specified for each
	// parameter
    $form = $app['form.factory']->createBuilder('form')
        ->add('username', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('password', 'password', array(
            'label' => 'Password',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('login', 'submit', array('label'=>'Login'))
        ->getForm();
    $form->handleRequest($request);

    // Once the form is validated, get the data from the form and query the database to 
    // verify the username and password are correct
    $msg = '';
    if ($form->isValid()) {
        $db = $app['db'];
        $regform = $form->getData();
        $uname = $regform['username'];
        $pword = $regform['password'];
        $query = "select password, custID 
        			from customer
        			where username = ?";
        $results = queryDB($db, $query, array($uname));
        # Ensure we only get one entry
        if (sizeof($results) == 1) {
            $retrievedPwd = $results[0][0];
            $custID = $results[0][1];

            // If the username and password are correct, create a login session for the user
            // The session variables are the username and the customer ID to be used in 
            // other queries for lookup.
            if (password_verify($pword, $retrievedPwd)) {
                $app['session']->set('is_user', true);
                $app['session']->set('user', $uname);
                $app['session']->set('custID', $custID);
                return $app->redirect('/streamTV/');
            }
            else{
                return $app->redirect('/streamTV/register');
            }
        }
        else {
        	$msg = 'Invalid User Name or Password - Try again';
        }
        
    }
    // Use the twig form template to display the login page
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Login',
        'form' => $form->createView(),
        'results' => $msg
    ));
});


// *************************************************************************

// Registration Page

$app->match('/register', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('uname', 'text', array(
            'label' => 'User Name',
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('password', 'repeated', array(
            'type' => 'password',
            'invalid_message' => 'Password and Verify Password must match',
            'first_options'  => array('label' => 'Password'),
            'second_options' => array('label' => 'Verify Password'),    
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 5)))
        ))
        ->add('fname', 'text', array(
            'label' => 'FirstName',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->add('lname', 'text', array(
            'label' => 'LastName',
            'constraints' => array(new Assert\NotBlank())    
        ))
        ->add('email', 'text', array(
            'label' => 'Email',
            'constraints' => array(new Assert\NotBlank(), new Assert\Email())
        ))
        //card # must be 16 characters long
        ->add('ccard', 'text', array(
            'label' => 'CreditCard',
            'constraints' => array(new Assert\NotBlank(), new Assert\Length(array('min' => 16)))
        ))
        ->add('submit', 'submit', array('label'=>'Register'))
        ->getForm();

    $form->handleRequest($request);

    if ($form->isValid()) {
        $regform = $form->getData();
        $uname = $regform['uname'];
        $pword = $regform['password'];
        $fname = $regform['fname'];
        $lname = $regform['lname'];
        $email = $regform['email'];
        $ccard = $regform['ccard'];
        
        // Check to make sure the username is not already in use
        // If it is, display already in use message
        // If not, hash the password and insert the new customer into the database
        $db = $app['db'];
        $query = 'select * from customer where username = ?';
        $results = queryDB($db, $query, array($uname));
        if ($results) {
    		return $app['twig']->render('form.html.twig', array(
        		'pageTitle' => 'Register',
        		'form' => $form->createView(),
        		'results' => 'Username already exists - Try again'
        	));
        }
        else { 
			$hashed_pword = password_hash($pword, PASSWORD_DEFAULT);

           
            //manking next custID (needs fix)
            $custid = NULL;
            $query = 'SELECT MAX(custID) FROM customer';
            $custresult = queryDB($db, $query, array(++$custid));

			$insertData = array($custid, $uname, $hashed_pword, $fname, $lname, $email, $ccard);
       	 	$query = 'insert into customer 
        				(custID, username, password, fname, lname, email, ccard)
        				values (?, ?, ?, ?, ?, ?, ?)';
        	$results = queryDB($db, $query, $insertData);
	        // Maybe already log the user in, if not validating email
        	return $app->redirect('/streamTV/');
        }
    }
    return $app['twig']->render('form.html.twig', array(
        'pageTitle' => 'Register',
        'form' => $form->createView(),
        'results' => ''
    ));   
});

// *************************************************************************
 
// SHOW Result Page
        //modifieD TOY SEARCH FOR SHOWS

$app->get('/search/{showID}', function (Silex\Application $app, $showID) {
    // Create query to get the toy with the given toynum
    $db = $app['db'];

    //find details about the show

    $query = "SELECT  shows.showID, shows.title, shows.network, shows.premiere_year, shows.creator, shows.catagory
    	   FROM shows
    	";
    $results = queryDB($db, $query, array($showID));

    //finds actors in main cast

    $query = "SELECT  actor.actID, actor.fname, actor.lname, main_cast.role
           FROM actor, shows, main_cast
           WHERE shows.showID = main_cast.showID AND actor.actID = main_cast.actID
        ";
    $actorResults = queryDB($db, $query, array($showID));

    //finds actors in recurring roles
    $query = "SELECT  actor.actID, actor.fname, actor.lname, recurring_cast.role
           FROM actor, shows, main_cast
           WHERE shows.showID = main_cast.showID AND actor.actID = main_cast.actID
        ";
    $actorResults = queryDB($db, $query, array($showID));
    
    // Display results in item page
    return $app['twig']->render('shows.html.twig', array(
        'pageTitle' => $results[0]['title'],
        'results' => $results
    ));
});
// *************************************************************************
//  SHOW EPISODE page


$app->get('/shows/{showID}', function (Silex\Application $app, $showID) {
    // Create query to get the show episodes sorted by episode ID
    $db = $app['db'];
    $query = "SELECT  episode.episodeID, episode.title, episode.airdate
           FROM shows, episode
           WHERE shows.showID = episode.showID
           ORDER BY episodeID ASC
        ";
    $results = queryDB($db, $query, array($showID));
    
    // Display results in item page
    return $app['twig']->render('showsEpisodes.html.twig', array(
        'pageTitle' => $results[0]['title'],
        'results' => $results
    ));
});


// *************************************************************************


//  EPISODE INFO page


$app->get('/episode/{episodeID}', function (Silex\Application $app, $episodeID) {
    // Create query to get the episode info
    $db = $app['db'];
    $query = "SELECT  FIRST(episode.episodeID), episode.title, episode.airdate
           FROM shows, episode,
           WHERE shows.showID = episode.showID
        ";
    $results = queryDB($db, $query, array($episodeID));


    //actors in main cast
    $query = "SELECT  actor.fname, actor.lname, role
           FROM shows, episode, main_cast
           WHERE actor.actID = recurring_cast.actID AND shows.showID = main_cast.showID
        ";
    $results = queryDB($db, $query, array($episodeID));


    //actors in recurring cast
     $query = "SELECT  actor.fname, actor.lname, role
           FROM shows, episode, recurring_cast
           WHERE actor.actID = recurring_cast.actID AND shows.showID = recurring_cast.showID
        ";
    $results = queryDB($db, $query, array($episodeID));
    
    // Display results in item page
    return $app['twig']->render('showsEpisodes.html.twig', array(
        'pageTitle' => $results[0]['title'],
        'results' => $results
    ));
});


// *************************************************************************

// Search Result Page

$app->match('/search', function (Request $request) use ($app) {
    $form = $app['form.factory']->createBuilder('form')
        ->add('search', 'text', array(
            'label' => 'Search',
            'constraints' => array(new Assert\NotBlank())
        ))
        ->getForm();
    $form->handleRequest($request);
    if ($form->isValid()) {
        $regform = $form->getData();
		$srch = $regform['search'];
		
		// Create prepared query 
        $db = $app['db'];

        //actor query
		$query = "SELECT actor.fname, actor.lname, actor.actID FROM actor where actor.fname like ? OR actor.lname like ?";
		$actorresults = queryDB($db, $query, array('%'.$srch.'%', '%'.$srch.'%'));

        //show query
        $query = "SELECT shows.title, shows.showID FROM shows where shows.title like ?";
        $showresults = queryDB($db, $query, array('%'.$srch.'%'));
		
        
        // Display results in search page
        return $app['twig']->render('search.html.twig', array(
            'pageTitle' => 'Search',
            'form' => $form->createView(),
            'actorresults' => $actorresults,
            'showresults' => $showresults
        ));
    }
    // If search box is empty, redisplay search page
    return $app['twig']->render('search.html.twig', array(
        'pageTitle' => 'Search',
        'form' => $form->createView(),
        'actorresults' => '',
        'showresults' => ''
    ));
});

// *************************************************************************

//Customer queue

$app->get('/queue/{custID}', function (Silex\Application $app, $custID) {
    // Create query to get the episode info
    $db = $app['db'];
   

    //shows in queue
    $query = "SELECT shows.showID, cust_queue.datequeued
           FROM shows, queue, customer
           WHERE shows.showID= cust_queue.showID
           AND cust_queue.custID = customer.custID
        ";
    $results = queryDB($db, $query, array($custID));


    
    // Display results in item page
    return $app['twig']->render('queue.html.twig', array(
        'pageTitle' => $results[0]['title'],
        'results' => $results
    ));
});


//  ******************************************************************************
//actor queue

$app->get('/actor/{actID}', function (Silex\Application $app, $actID) {
    // Create query to get the episode info
    $db = $app['db'];
   

    //shows in queue
    $query = "SELECT actor.actID, actor.fname, actor.lname
           FROM actor";
    $results = queryDB($db, $query, array($actID));


    
    // Display results in item page
    return $app['twig']->render('actor.html.twig', array(
        'pageTitle' => $results[0]['title'],
        'results' => $results
    ));
});

        
// *************************************************************************
		
// *************************************************************************

// Logout FOR STREAM TV

$app->get('/logout', function () use ($app) {
	$app['session']->clear();
	return $app->redirect('/streamTV/');
});
	
// *************************************************************************

// Home Page

$app->get('/', function () use ($app) {
	if ($app['session']->get('is_user')) {
		$user = $app['session']->get('user');
	}
	else {
		$user = '';
	}
	return $app['twig']->render('home.twig', array(
        'user' => $user,
        'pageTitle' => 'Home'));
});

// *************************************************************************

// Run the Application

$app->run();