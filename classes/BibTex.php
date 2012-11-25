<?php
    
class BibTex 
{
    public function read($value)
    {            
        $entrys =   explode('@', $value);
        $return =   array();

        foreach($entrys as $entry) {
            if ($entry !== '') {
                $return[]   =   $this->parse('@' . $entry);
            }
        }

        return $return;
    }

    private function parse($entry)
    {
        $entry  =   $this->clean($entry);
        $return =   array(
                        'note'          =>  array(),
                        'journal'       =>  '', 
                        'year'          =>  '',
                        'publisher'     =>  '',
                        'booktitle'     =>  '',
                        'chapter'       =>  '',
                        'pages'         =>  '',
                        'address'       =>  '', 
                        'school'        =>  '',
                        'institution'   =>  '');

        // get type of book
        $entry  = str_replace('`,', "`,\n", $entry);
        $entry  = str_replace('},', "},\n", $entry);        
        preg_match('/@([A-z]+)\{/', $entry, $type);
        if (isset($type[1]) == true) {
            $return['type'] = ucfirst($type[1]);
        }
        else {
            $return['type'] =   'unknown';
        }

        // get values of fields fields
        preg_match_all('/([A-z]+) = [`|{](.*)[`|}],/', $entry, $value);

        foreach($value[1] as $number => $field) {
            switch($field) {
                case 'author':
                    $return['author']   =   str_replace(' and ', ';', $value[2][$number]);
                break;

                case 'note':
                    $return['note'][]   =   $value[2][$number];
                break;    

                default:
                    $return[$field] =   $value[2][$number];
                break;
            }
        }           

        return $return;
    }

    private function clean($entry)
    {
        $search = array(	
                     chr(92),   //	\
                     chr(35),   //	#
                     chr(36),   //	$
                     chr(37),   //	%
                     chr(38),   //	&
                     chr(45),   //  -
                     chr(60),   //	<
                     chr(62),   //	>
                     chr(95),   //	_
                     chr(126),  //	~
                     chr(161),  //	Â¡
                     chr(163),  //	Â£
                     chr(165),  //	Â¥
                     chr(167),  //	Â§
                     chr(168),  //	Â¨
                     chr(169),  //	Â©
                     chr(170),  //	Âª
                     chr(171),  //	Â«
                     chr(172),  //	Â¬
                     chr(174),  //	Â®
                     chr(181),  //	Âµ
                     chr(182),  //	Â¶
                     chr(187),  //	Â»
                     chr(191),  //	Â¿
                     'Ã€',  //	Ã€
                     'Ã',  //	Ã
                     'Ã‚',  //	Ã‚
                     'Ãƒ',
                     'Ã„',
                     'Ã…',
                     'Ã†',
                     'Ã‡',
                     'Ãˆ',
                     'Ã‰',
                     'ÃŠ',
                     'Ã‹',
                     'ÃŒ',
                     'Ã',
                     'ÃŽ',
                     'Ã',
                     'Ã‘',
                     'Ã’',
                     'Ã“',
                     'Ã”',
                     'Ã•',
                     'Ã–',
                     'Ã—',
                     'Ã˜',
                     'Ã™',
                     'Ãš',
                     'Ã›',
                     'Ãœ',
                     'Ã',
                     'ÃŸ',
                     'Ã ',
                     'Ã¡',
                     'Ã¢',
                     'Ã£',
                     'Ã¤', 
                     'Ã¥',
                     'Ã¦',
                     'Ã§',
                     'Ã¨',
                     'Ã©',
                     'Ãª',
                     'Ã«',
                     'Ã¬',
                     'Ã­',
                     'Ã®',
                     'Ã¯',
                     'Ã±',
                     'Ã²',
                     'Ã³',
                     'Ã´',
                     'Ãµ',
                     'Ã¶',
                     'Ã¸',
                     'Ã¹',
                     'Ãº',
                     'Ã»',
                     'Ã¼',
                     'Ã½',
                     'Ã¿',                                         
                     'Ã„', 
                     'Ã‹',
                     'Ã',
                     'Ã–',
                     'Ãœ',
                     'Ã¤',
                     'Ã«',
                     'Ã¯',
                     'Ã¶',
                     'Ã¼',
                     'Ã¿'
             );
            $replace = array(
                "{\$\\backslash\$}",       //	\
                "{\\#}",                   //	#
                "{\\$}",                   //	$
                "{\\%}",                   //	%
                "{\\&}",                   //	&
                "--",                      //  -
                "{\$\<\$}",                //	<
                "{\$\<\$}",                //	>
                "{\\_}",                   //	_
                "{\\~{}}",                 //	~
                "{!'}",                    //	Â¡
                "{\\pounds}",              //	Â£
                "{\\yen}",                 //	Â¥
                "{\\S}",                   //	Â§
                "{\\\"~}",                 //	Â¨
                "{\\copyright}",           //	Â©
                "\\textsuperscript{2}",    //	Âª
                "{\$\\guillemotleft\$}",   //	Â«
                "{\$\\lnot\$}",            //	Â¬
                "{\\textregistered}",      //	Â®
                "{\$\\mu\$}",              //	Âµ
                "{\$\\pi\$}",              //	Â¶
                "{\$\\guillemotleft\$}",   //	Â»
                "{?'}",                    //	Â¿
                "{\\`A}",                  //	Ã€
                "{\\'A}",                  //	Ã
                "{\\^A}",                  //	Ã‚
                "{\\~A}",                  //	Ãƒ
                "{\\\"A}",                 //	Ã„
                "{\\AA}",                  //	Ã…
                "{\\AE}",                  //	Ã†
                "{\\c{C}}",                //	Ã‡
                "{\\`E}",                  //	Ãˆ
                "{\\'E}",                  //	Ã‰
                "{\\^E}",                  //	ÃŠ
                "{\\\"E}",                 //	Ã‹
                "{\\`I}",                  //	ÃŒ
                "{\\'I}",                  //	Ã
                "{\\^I}",                  //	ÃŽ
                "{\\\"I}",                 //	Ã
                "{\\~N}",                  //	Ã‘
                "{\\`O}",                  //	Ã’
                "{\\'O}",                  //	Ã“
                "{\\^O}",                  //	Ã”
                "{\\~O}",                  //	Ã•
                "{\\\"O}",                 //	Ã–
                "{\$\\times\$}",           //	Ã—
                "{\\O}",                   //	Ã˜
                "{\\`U}",                  //	Ã™
                "{\\'U}",                  //	Ãš
                "{\\^U}",                  //	Ã›
                "{\\\"U}",                 //	Ãœ
                "{\\'y}",                  //	Ã
                "{\\ss}",                  //	ÃŸ
                "{\\`a}",                  //	Ã 
                "{\\'a}",                  //	Ã¡
                "{\\^a}",                  //	Ã¢
                "{\\~a}",                  //	Ã£
                "{\\\"a}",                  //	Ã¤
                "{\\aa}",                  //	Ã¥
                "{\\ae}",                  //	Ã¦
                "{\\c{c}}",                //	Ã§
                "{\\`e}",                  //	Ã¨
                "{\\'e}",                  //	Ã©
                "{\\~e}",                  //	Ãª
                "{\\\"e}",                 //	Ã«
                "{\\`\\i}",                //	Ã¬
                "{\\'\\i}",                //	Ã­
                "{\\^\\i}",                //	Ã®
                "{\\\"\\i}",               //	Ã¯
                "{\\~n}",                  //	Ã±
                "{\\`o}",                  //	Ã²
                "{\\'o}",                  //	Ã³
                "{\\^o}",                  //	Ã´
                "{\\~o}",                  //	Ãµ
                "{\\\"o}",                 //	Ã¶
                "{\\o}",                   //	Ã¸
                "{\\`u}",                  //	Ã¹
                "{\\'u}",                  //	Ãº
                "{\\^u}",                  //	Ã»
                "{\\\"u}",                 //	Ã¼
                "{\\'y}",                  //	Ã½
                "{\\\"y}",                  //	Ã¿

                "{\\\"A}",                 //	Ã„
                "{\\\"E}",                 //	Ã‹
                "{\\\"I}",                 //	Ã
                "{\\\"O}",                 //	Ã–
                "{\\\"U}",                 //	Ãœ
                "{\\\"a}",                  //	Ã¤
                "{\\\"e}",                 //	Ã«
                "{\\\"\\i}",               //	Ã¯
                "{\\\"o}",                 //	Ã¶
                "{\\\"u}",                 //	Ã¼
                "{\\\"y}"                  //	Ã¿                                        
            );
        
            $entry = str_replace($replace, $search, $entry );
            $entry = str_replace( "'", "`", $entry );   // replacing single quotes.
            $entry = str_replace( "\"", "`", $entry );  // replacing double quotes.
            $entry = str_replace( "\n", "", $entry );  // replacing line feeds.
            $entry = stripslashes( $entry );            // removing backslashes.

            return $entry;	
    }
}