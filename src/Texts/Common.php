<?php
namespace Texts;

/**
 * Class Common
 * @package Texts
 * @author Igor Borodikhin <gmail@iborodikhin.net>
 */
class Common
{
    /**
     * Local cache for stop-words.
     *
     * @var array
     */
    protected static $stopWords = array();

    /**
     * Create short announce for text with title.
     *
     * @param string $title
     * @param string $content
     * @param integer $returnSentances
     * @return string
     */
    public static function annotate($title = '', $content = '', $returnSentances = 3)
    {
        $replaces = array(
            '#'.preg_replace('#\s+#u', '\\s+', $title).'#iu' => '',
            '#\s+#iu'                                        => ' ',
            '#<table.*</table>#u'                            => '',
            '#<code.*</code>#u'                              => '',
        );
        $stopWords     = self::getStopWords('ru');
        $content       = preg_replace(array_keys($replaces), array_values($replaces), $content);
        $content       = strip_tags($content);
        $content_split = preg_replace('#[.!?\s]\s+([А-ЯA-Z0-9])#u', '<sent>\\1', $content);
        $sentences     = preg_split('#<sent>#iu', $content_split, -1, PREG_SPLIT_NO_EMPTY);
        $title         = ' ' . $title . ' ';

        $replaces = array(
            '#\s#'                                => '  ',
            '#[^a-zа-я][a-zа-я]{1,3}[^a-zа-я]#iu' => '',
            '#[^a-zа-я\s]#iu'                     => ' ',
            '#\s+#iu'                             => ' ',
        );

        $title   = preg_replace(array_keys($replaces), array_values($replaces), $title);
        $words   = preg_split('#\s+#iu', $title);
        $words   = array_map(array(get_called_class(), 'stemWord'), $words);
        $words   = array_filter($words, function ($item) use ($stopWords) {
            return !in_array($item, $stopWords);
        });
        $matches = array();

        foreach ($words as $word) {
            if (mb_strlen($word, 'utf-8') > 4) {
                str_ireplace($word, '', $content, $tf);

                foreach ($sentences as $sentence) {
                    if (!empty($sentence)) {
                        str_ireplace($word, '', $sentence, $idf);
                        if (!isset($matches[$sentence])) {
                            $matches[$sentence] = ($tf)*($idf);
                        } else {
                            $matches[$sentence] += ($tf)*($idf);
                        }
                    }
                }
            }
        }

        arsort($matches);

        $matches = array_filter(array_flip($matches));
        $matched = array();

        for ($i = 0; $i < max(count($matches), $returnSentances); $i++) {
            $matched[] = array_shift($matches);
        }

        usort($matched, function ($a, $b) use ($content) {
            $aPos = strpos($content, $a);
            $bPos = strpos($content, $b);

            if ($aPos === $bPos) {
                return 0;
            }

            return ($aPos > $bPos ? 1 : -1);
        });

        $matched = array_map(function ($item) {
            return preg_replace('#[.!?]+#u', '.', $item);
        }, $matched);

        $match = implode(" ", $matched) . "…";
        $match = preg_replace('#\s+#iu', ' ', $match);

        return $match;
    }

    /**
     * Porter Stemming.
     * {@see http://forum.dklab.ru/php/advises/HeuristicWithoutTheDictionaryExtractionOfARootFromRussianWord.html}
     *
     * @param string $word
     * @return string
     */
    public static function stemWord($word)
    {
        $VOWEL = '/аеиоуыэюя/';
        $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/u';
        $REFLEXIVE = '/(с[яь])$/u';
        $ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|еых|ую|юю|ая|яя|ою|ею)$/u';
        $PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/u';
        $VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/u';
        $NOUN = '/(а|ев|ов|ие|ье|е|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|и|ы|ь|ию|ью|ю|ия|ья|я)$/u';
        $RVRE = '/^(.*?[аеиоуыэюя])(.*)$/u';
        $DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/u';

        $s = function(&$subject, $re, $to)
        {
            $original = $subject;
            $subject  = preg_replace($re, $to, $subject);

            return $original !== $subject;
        };

        $word = str_replace("ё", "е", $word);
        $stem = $word;

        do {
            if (!preg_match($RVRE, $word, $p)) break;
            $start = $p[1];
            $RV    = $p[2];

            if (!$RV) {
                break;
            }

            // Step 1
            if (!$s($RV, $PERFECTIVEGROUND, '')) {
                $s($RV, $REFLEXIVE, '');

                if ($s($RV, $ADJECTIVE, '')) {
                    $s($RV, $PARTICIPLE, '');
                } else {
                    if (!$s($RV, $VERB, '')) {
                        $s($RV, $NOUN, '');
                    }
                }
            }

            // Step 2
            $s($RV, '/и$/', '');

            // Step 3
            if (preg_match($DERIVATIONAL, $RV)) {
                $s($RV, '/ость?$/u', '');
            }

            // Step 4
            if (!$s($RV, '/ь$/u', '')) {
                $s($RV, '/ейше?/u', '');
                $s($RV, '/нн$/u', 'н');
            }

            $stem = $start . $RV;
        } while(false);

        return $stem;
    }

    /**
     * Converts underscore notation to camel-case notation.
     *
     * @param string $varName
     * @return string
     */
    public static function camelize($varName)
    {
        return preg_replace("#(([^a-z0-9])([a-z0-9]{1}))#iue", "strtoupper('$3')", $varName);
    }

    /**
     * Converts camel-case notation to underscore notation.
     *
     * @param string $varName
     * @return string
     */
    public static function underscorize($varName)
    {
        return preg_replace("#([A-Z]{1})#ue", "_.strtolower('$1')", $varName);
    }

    /**
     * Transliterates russian string.
     *
     * @param  string $text
     * @return string
     */
    public static function transliterate($text)
    {
        $uppers = array();
        $trans  = array(
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i' ,'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        );

        foreach ($trans as $ru => $en) {
            $uppers[mb_strtoupper($ru, 'utf-8')] = ucfirst($en);
        }

        return strtr($text, array_merge($trans, $uppers));
    }

    /**
     * Loads stop-words for selected language.
     *
     * @param string $language
     * @return array
     * @throws \InvalidArgumentException
     */
    protected static function getStopWords($language = 'ru')
    {
        if (!isset(self::$stopWords[$language])) {
            $dir   = realpath(__DIR__ . '/../../data');
            $file  = sprintf('%s/stop_%s.dat', $dir, $language);

            if (!is_readable($file)) {
                throw new \InvalidArgumentException(sprintf("Stop-words for language «%s» not found", $language));
            }

            $words = file($file);
            self::$stopWords[$language] = array_map('trim', $words);
        }

        return self::$stopWords[$language];
    }

}