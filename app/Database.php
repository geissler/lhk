<?php

/**
 * @property Doctrine\DBAL\Connection $db Database Connection
 */
class Database
{
    private $db;
    
    public function __construct(Doctrine\DBAL\Connection $db)
    {
        $this->db   =   $db;
    }
    
    public function clear($type)
    {
        $result     =   $this->db->fetchAll('SELECT id FROM title WHERE type = ?', array($type));
        $titleIds   =   array();
        $length     =   count($result);
        
        if ($length > 0) {
            for($i = 0; $i < $length; $i++) {            
                $titleIds[] =   $result[$i]['id'];
            }            
            $questionMarks  = implode(', ', array_fill(0, count($titleIds), '?'));

            $this->db->executeQuery('DELETE FROM quotes WHERE titleid IN (' . $questionMarks . ')', $titleIds);
            $this->db->executeQuery('DELETE FROM signatur WHERE titleid IN (' . $questionMarks . ')', $titleIds);
            $this->db->executeQuery('DELETE FROM keywords WHERE titleid IN (' . $questionMarks . ')', $titleIds);
            $this->db->executeQuery('DELETE FROM title WHERE id IN (' . $questionMarks . ')', $titleIds);
        }
    }
    
    public function reinstall()
    {
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
            $this->db->executeQuery($sql);   
        }     
    }
}
