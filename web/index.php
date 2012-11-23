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
            'geissler' => array('ROLE_ADMIN', $_SERVER['APP_IMPORT']),
        ),
    ),
);
$app->boot();

// Database
$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'    =>  'pdo_mysql',
        'host'      =>  $_SERVER['DB1_HOST'],
        'port'      =>  $_SERVER['DB1_PORT'],
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
    if ($search !== '') {
        $array  = explode(' ', $search);

        foreach ($array as $value) {
            $text   =   preg_replace('/(' . $value . ')/i', '<span style="background-color:yellow">\1</span>', $text);
        }
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
                            LEFT JOIN quotes ON title.id = quotes.titleid
                            LEFT JOIN keywords ON title.id = keywords.id
                            LEFT JOIN signatur ON title.id = signatur.titleid
                            WHERE 
                                quotes.quote LIKE ' . implode(' AND quotes.quote LIKE', $parameter);
        
        $sqlTitle   =   'SELECT title, keywords, signatur
                            FROM title
                            LEFT JOIN keywords ON title.id = keywords.id
                            LEFT JOIN signatur ON title.id = signatur.titleid
                            WHERE                             
                                (keywords.keywords LIKE ' . implode(' AND keywords.keywords LIKE', $parameter) .')
                                OR (title.title LIKE ' . implode(' AND title.title LIKE', $parameter) .')
                                OR (title.type LIKE ' . implode(' AND title.type LIKE', $parameter) .')';

        for($i = 0; $i < count($search); $i++) {
            $search[$i] =   '%' . $search[$i] . '%';
        }
        
        $result = array_merge(
                        $app['db']->fetchAll($sqlQuotes, $search),
                        $app['db']->fetchAll($sqlTitle, array_merge($search, $search, $search)));
    }
    
    return $app['twig']->render('index.html.twig', array(
        'result' => $result,
        'hits'  => count($result),
        'search' => $request->get('search')
    ));    
});

// Import Tabelle löschen
$app->get('/import/clear', function (Silex\Application $app) {       
    $sqls   =   array(
        'DROP TABLE IF EXISTS title',
        'DROP TABLE IF EXISTS  quotes',
        'DROP TABLE IF EXISTS  signatur',
        'DROP TABLE IF EXISTS  keywords',
        'CREATE TABLE `keywords` (`id` int(11) NOT NULL AUTO_INCREMENT,`titleid` int(11) NOT NULL,`keywords` text COLLATE utf8_unicode_ci NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci',
        'CREATE TABLE `quotes` (`id` int(11) NOT NULL AUTO_INCREMENT,`titleid` int(11) NOT NULL,`header` varchar(300) COLLATE utf8_unicode_ci NOT NULL,`quote` text COLLATE utf8_unicode_ci NOT NULL,`page` varchar(100) COLLATE utf8_unicode_ci NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci',
        'CREATE TABLE `signatur` (`id` int(11) NOT NULL AUTO_INCREMENT,`titleid` int(11) NOT NULL,`signatur` varchar(100) COLLATE utf8_unicode_ci NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci',
        'CREATE TABLE `title` (`id` int(11) NOT NULL,`title` varchar(500) COLLATE utf8_unicode_ci NOT NULL, `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
        
    foreach($sqls as $sql) {
        $app['db']->executeQuery($sql);   
    }       
    
    return $app['twig']->render('index.html.twig', array(
        'message' => 'Tabelle gelöscht und neu erstellt',
    ));
});

// Daten importieren
$app->get('/import/{name}', function (Silex\Application $app, $name) {
    require_once __DIR__ . '/../app/BibTex.php';
            
    $imports    =   array(
        'eisen'     =>  'Eisen',
        'lhk'       =>  'Italische Heiligtümer in der Kaiserzeit',
        'tonart'    =>  'Tonart',
        'forum'     =>  'Forum Romanum',
        'kolonien'  =>  'Latinische Kolonien',
        'medizin'   =>  'Arzthäuser in Pompeij',
        'gips'      =>  'Museum Bonn');
    
    if (array_key_exists($name, $imports) == true) {
        $id         =   $app['db']->fetchAssoc('SELECT MAX( id ) AS Max FROM  `title`');        
        $titleid    =   $id['Max'];
        $type       =   $imports[$name];        
        $titles     =   explode('<br>', utf8_encode(file_get_contents(__DIR__ . '/../files/' . $name . '.title.txt')));
        $bibTex     =   new BibTex();    
        $bib        =   $bibTex->read(utf8_encode(file_get_contents(__DIR__ . '/../files/' . $name . '.quotes.txt')));
            
        $length =   count($titles);
        for($i = 0; $i < $length; $i++) {
            // title
            $titleid++;            
            $app['db']->insert('title', array('id' => $titleid, 'title' => $titles[$i], 'type' => $type));
        
            // quotes
            if (isset($bib[$i]['note']) == true) {
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
                            $app['db']->insert('quotes', array('titleid' => $titleid,  'quote' => $quote, 'page'  =>  $page));                                                                 
                        }
                    }
                }        
            }

            // keywords
            if (isset($bib[$i]['keywords']) == true
                && $bib[$i]['keywords'] !== '') {
                    $app['db']->insert('keywords', array('titleid' => $titleid,  'keywords' => $bib[$i]['keywords'])); 
            }

            // Signatur
            if (isset($bib[$i]['LCCN']) == true
                && $bib[$i]['LCCN'] !== '') {
                    $app['db']->insert('signatur', array('titleid' => $titleid,  'signatur' => $bib[$i]['LCCN'])); 
            }            
        }    
    }
    
    return $app['twig']->render('index.html.twig', array(
        'message' => 'Daten erfolgreich importiert!',
    ));
});

// list
$app->get('/list', function (Silex\Application $app) {    
    $sql   =   'SELECT title, keywords, signatur, type
                    FROM title
                    LEFT JOIN keywords ON title.id = keywords.id
                    LEFT JOIN signatur ON title.id = signatur.titleid
                    ORDER BY title.type';

    return $app['twig']->render('index.html.twig', array(
        'result' => $app['db']->fetchAll($sql),
        'search' => '',
        'list'  =>  true
    ));   
});

// Silex ausführen
$app->run();