<?php

require_once __DIR__.'/../vendor/autoload.php';

// Silex
$app = new Silex\Application();
$app['debug'] = true;

// Security
$app['security.firewalls'] = array();
$app->register(new Silex\Provider\SecurityServiceProvider(array('security.firewalls' => array('import'))));
$app['security.firewalls'] = array(
    'import' => array(
        'pattern' => '^/import',
        'http' => true,
        'users' => array(
            // raw password is foo
            'geissler' => array('ROLE_ADMIN', $_SERVER["APP_IMPORT"]),
        ),
    ),
);
$app->boot();

// Database
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'    =>  'pdo_mysql',
        'host'      =>  $_SERVER['DB1_HOST'],
        'port'      =>  $_SERVER["DB1_PORT"],
        'dbname'    =>  $_SERVER['DB1_NAME'],
        'user'      =>  $_SERVER['DB1_USER'],
        'password'  =>  $_SERVER['DB1_PASS'],
    ),
));

// Twig
/**
 * Gesuchte Wörter hervorheben
 * 
 * @param string $text
 * @param string $search
 * @return string
 */
function highlight($text, $search) 
{
    $array  = explode(' ', $search);
    
    foreach ($array as $value) {
        $text   =   preg_replace('/(' . $value . ')/i', '<span style="background-color:yellow">\1</span>', $text);
    }
    
    return $text;
}
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__. '/../views',
));
$app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
    $twig->addFilter('highlight', new Twig_Filter_Function('highlight'));
    return $twig;
}));

// Start 
$app->get('', function () use ($app) {
    return $app['twig']->render('index.html.twig', array(
        'message' => 'Eine einfache Seite um die Literatur und Zitate, die ich im Zuge meiner Dissertation gesammelt habe, zu durch forsten.',
    ));
});

// Help
$app->get('/help', function () use ($app) {
    return $app['twig']->render('index.html.twig', array(
        'about' => true,
    ));
});

// Search
use Symfony\Component\HttpFoundation\Request;

$app->post('/search', function (Request $request, Silex\Application $app) {
    $search = explode(' ', $request->get('search'));
    $result = array();
    
    if (count($search) > 0) {
        $parameter  = array_fill(0, count($search), '?');
        $sqlQuotes  =   'SELECT title, quote, page, keywords, signatur
                            FROM title
                            JOIN quotes ON title.id = quotes.titleid
                            JOIN keywords ON title.id = keywords.id
                            JOIN signatur ON title.id = signatur.titleid
                            WHERE 
                                quotes.quote LIKE ' . implode(' AND quotes.quote LIKE', $parameter);
        
        $sqlTitle   =   'SELECT title, keywords, signatur
                            FROM title
                            JOIN keywords ON title.id = keywords.id
                            JOIN signatur ON title.id = signatur.titleid
                            WHERE                             
                                (keywords.keywords LIKE ' . implode(' AND keywords.keywords LIKE', $parameter) .')
                                OR (title.title LIKE ' . implode(' AND title.title LIKE', $parameter) .')';

        for($i = 0; $i < count($search); $i++) {
            $search[$i] =   '%' . $search[$i] . '%';
        }
        
        $result = array_merge(
                        $app['db']->fetchAll($sqlQuotes, $search),
                        $app['db']->fetchAll($sqlTitle, array_merge($search, $search)));
    }
    
    return $app['twig']->render('index.html.twig', array(
        'result' => $result,
        'search' => $request->get('search')
    ));    
});

// Import
$app->get('/import', function (Silex\Application $app) {
    require_once __DIR__ . '/../app/BibTex.php';
    
    $titles =   explode('<br>', file_get_contents(__DIR__ . '/../files/title.txt'));
    $bibTex =   new BibTex();    
    $bib    =   $bibTex->read(utf8_encode(file_get_contents(__DIR__ . '/../files/quotes.txt')));
    
    $sql = 'TRUNCATE title';
    $app['db']->executeQuery($sql);   
    
    $sql = 'TRUNCATE quotes';
    $app['db']->executeQuery($sql);    
   
    $sql = 'TRUNCATE signatur';
    $app['db']->executeQuery($sql); 
    
    $sql = 'TRUNCATE keywords';
    $app['db']->executeQuery($sql); 
    
    $length =   count($titles);
    for($i = 0; $i < $length; $i++) {
        $app['db']->insert('title', array('id' => $i, 'title' => $titles[$i]));
    }
    
    for($i = 0; $i < $length; $i++) {
        $numberOfQuotes =   count($bib[$i]['note']);
        if ($numberOfQuotes > 0) {
            for($j = 0; $j < $numberOfQuotes; $j++) {
                if (preg_match('/([0-9]+\-)?([0-9]+)(f| f|f\.| f\.| f \.)?$/', $bib[$i]['note'][$j], $pages) == 1) {                        
                    // save page                             
                    $page  =   $pages[0];
                    if (strpos($pages[0], 'f') !== false) {
                        $page  =   trim(str_replace(array('f', '.'), array('', ''), $pages[0]));                            
                    }
                    $page   = preg_replace('/^0/', '', $page);
                    
                    // save quote
                    $quote =   trim(str_replace('``', '"', str_replace($pages[0], '', $bib[$i]['note'][$j])));
                    $app['db']->insert('quotes', array('titleid' => $i,  'quote' => $quote, 'page'  =>  $page));                                                                 
                }
            }
        }        
        
        // keywords
        if (isset($bib[$i]['keywords']) == true
            && $bib[$i]['keywords'] !== '') {
                $app['db']->insert('keywords', array('titleid' => $i,  'keywords' => $bib[$i]['keywords'])); 
        }
        
        // Signatur
        if (isset($bib[$i]['LCCN']) == true
            && $bib[$i]['LCCN'] !== '') {
                $app['db']->insert('signatur', array('titleid' => $i,  'signatur' => $bib[$i]['LCCN'])); 
        }
    }    
    
    return $app['twig']->render('index.html.twig', array(
        'message' => 'Daten erfolgreich importiert!',
    ));
});

// list
$app->get('/list', function (Silex\Application $app) {    
    $sql   =   'SELECT title, keywords, signatur
                        FROM title
                        JOIN keywords ON title.id = keywords.id
                        JOIN signatur ON title.id = signatur.titleid';

    return $app['twig']->render('index.html.twig', array(
        'result' => $app['db']->fetchAll($sql),
        'search' => ''
    ));   
});

// Silex ausführen
$app->run();