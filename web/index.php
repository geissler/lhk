<?php

require_once __DIR__.'/../vendor/autoload.php';

// Silex
$app = new Silex\Application();
$app['debug'] = $_SERVER['APP_DEBUG'];

// Security
$app['security.firewalls'] = array();
$app->register(new Silex\Provider\SecurityServiceProvider(array('security.firewalls' => array('import'))));
$app['security.firewalls'] = array(
    'import' => array(
        'pattern' => '^/import',
        'http' => true,
        'users' => array(
            // raw password is foo
            $_SERVER['APP_USER'] => array('ROLE_ADMIN', $_SERVER['APP_PASSWORD']),
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

// Translation
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallback' => 'de',
));

// Forms
use Silex\Provider\FormServiceProvider;
$app->register(new FormServiceProvider());
$app->register(new Silex\Provider\ValidatorServiceProvider());

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
            $value  =   str_replace('?', '.', $value);            
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
    return $app['twig']->render('about.html.twig', array());
});

// Search
use Symfony\Component\HttpFoundation\Request;
$app->get('/search', function (Request $request, Silex\Application $app) {
    $search     =   str_replace('?', '_', $request->get('search'));
    $search     =   str_replace('*', '%', $search);
    $searchFor  =   explode(' ', $search);
    $result     =   array();
    
    if (count($searchFor) > 0) {
        $parameter  = array_fill(0, count($searchFor), '?');
        $sqlQuotes  =   'SELECT title, quote, page, keywords, signatur
                            FROM title
                            LEFT JOIN quotes ON title.id = quotes.titleid
                            LEFT JOIN keywords ON title.id = keywords.titleid
                            LEFT JOIN signatur ON title.id = signatur.titleid
                            WHERE 
                                quotes.quote LIKE ' . implode(' AND quotes.quote LIKE', $parameter);
        
        $sqlTitle   =   'SELECT title, keywords, signatur
                            FROM title
                            LEFT JOIN keywords ON title.id = keywords.titleid
                            LEFT JOIN signatur ON title.id = signatur.titleid
                            WHERE                             
                                (keywords.keywords LIKE ' . implode(' AND keywords.keywords LIKE', $parameter) .')
                                OR (title.title LIKE ' . implode(' AND title.title LIKE', $parameter) .')
                                OR (title.type LIKE ' . implode(' AND title.type LIKE', $parameter) .')';

        for($i = 0; $i < count($searchFor); $i++) {
            $searchFor[$i] =   '%' . $searchFor[$i] . '%';
        }
       
        $result = array_merge(
                        $app['db']->fetchAll($sqlQuotes, $searchFor),
                        $app['db']->fetchAll($sqlTitle, array_merge($searchFor, $searchFor, $searchFor)));
    }
    
    return $app['twig']->render('search.html.twig', array(
        'result'    =>  $result,
        'hits'      =>  count($result),
        'search'    =>  $request->get('search')
    ));    
});

// display quotes to title
$app->get('/quotes/{id}', function (Silex\Application $app, $id) {
    $sql    =   'SELECT title, quote, page, keywords, signatur
                    FROM title
                    LEFT JOIN quotes ON title.id = quotes.titleid
                    LEFT JOIN keywords ON title.id = keywords.titleid
                    LEFT JOIN signatur ON title.id = signatur.titleid
                    WHERE title.id = ?';
    
    return $app['twig']->render('quotes.html.twig', array(
        'result' => $app['db']->fetchAll($sql, array($id))
    ));
});

// reinstall all tables
$app->get('/import/reinstall', function (Silex\Application $app) {     
    require_once __DIR__ . '/../classes/Database.php';
    
    $db = new Database($app['db']);
    $db->reinstall();
    
    return $app['twig']->render('message.html.twig', array(
        'message' => 'Tabelle gelöscht und neu erstellt',
    ));
});

// import data
$app->match('/import', function (Request $request, Silex\Application $app) {
    $form   =   $app['form.factory']->createBuilder('form')
                                    ->add('type', null, array(
                                            'label' => 'Literatur Liste'))
                                    ->add('titles', 'file', array(
                                            'label' => 'Datei mit den Literaturnachweisen'))
                                    ->add('quotes', 'file', array(
                                            'label' => 'Datei mit den BibTex Angaben'))      
                                    ->add('encode', 'checkbox', array(
                                            'label' => 'Encodierung nicht überprüfen',
                                            'required' => false))
                                    ->getForm();
    
    if ('POST' == $request->getMethod()) {        
        $form->bind($request);

        if ($form->isValid()) {
            require_once __DIR__ . '/../classes/Database.php';
            require_once __DIR__ . '/../classes/BibTex.php';
            $uploadDir  =   __DIR__ . '/../files';
            $data       =   $form->getData();
            
            // remove
            $db = new Database($app['db']);
            $db->clear($data['type']);
            
            // upload files            
            $form['titles']->getData()->move($uploadDir, 'titles.txt');
            $form['quotes']->getData()->move($uploadDir, 'quotes.txt');
           
            // init data
            $id         =   $app['db']->fetchAssoc('SELECT MAX( id ) AS Max FROM  `title`');        
            $titleid    =   $id['Max'];
            $type       =   $data['type'];    
            
            // load titles
            $loadTitles = file_get_contents($uploadDir . '/titles.txt');
            if ($data['encode'] == false) {
                if (mb_detect_encoding($loadTitles) !== 'UTF-8') {
                    $loadTitles =   mb_convert_encoding($loadTitles, 'utf-8');
                }
                else {
                    $loadTitles    = utf8_encode($loadTitles);
                }    
            }                        
            $titles     =   explode('<br>', $loadTitles);   
            
            // load quotes             
            $loadQuotes = file_get_contents($uploadDir . '/quotes.txt');
            if (mb_detect_encoding($loadQuotes) !== 'UTF-8') {
                $loadQuotes =   mb_convert_encoding($loadQuotes, 'utf-8');
            }
            else {
                $loadQuotes    = utf8_encode($loadQuotes);
            }  
            $bibTex =   new BibTex();                
            $bib    =   $bibTex->read($loadQuotes);
            
            // import data
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
            
            // redirect somewhere
            return $app['twig']->render('message.html.twig', array(
                'message' => 'Daten erfolgreich importiert!',
            ));
        }
    }
    
    return $app['twig']->render('import.html.twig', array(
        'form' => $form->createView()
    ));
});

// remove literatur list
$app->get('/import/remove/{name}', function (Silex\Application $app, $name) {
    require_once __DIR__ . '/../classes/Database.php';
    
    $db = new Database($app['db']);
    $db->clear($name);
    
    return $app['twig']->render('message.html.twig', array(
        'message' => 'Literatur zur <b>' . $name . '</b> gelöscht',
    ));
});

// list all entrys
$app->get('/list', function (Silex\Application $app) {    
    $sql   =   'SELECT title.id, title, keywords, signatur, type
                    FROM title
                    LEFT JOIN keywords ON title.id = keywords.titleid
                    LEFT JOIN signatur ON title.id = signatur.titleid
                    ORDER BY title.type';
       
    return $app['twig']->render('list.html.twig', array(
        'result' => $app['db']->fetchAll($sql),
        'types'  => $app['db']->fetchAll('SELECT type FROM title GROUP BY type')
    ));   
});

// display tag cloud
$app->get('/tags', function (Silex\Application $app) {
    include __DIR__ . '/../classes/tagcloud.php';

    $cloud  =   new tagcloud();
    $sql    =   'SELECT * FROM keywords';
    $result = $app['db']->fetchAll($sql);
    $length =   count($result);
        
    for($i = 0; $i < $length; $i++) {
        $cloud->addTags(explode(' ', $result[$i]['keywords']));        
    }
    
    return $app['twig']->render('tags.html.twig', array(
        'tags' => $cloud->render('array')
    ));
});

// Silex ausführen
$app->run();