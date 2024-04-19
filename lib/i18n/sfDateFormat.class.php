<?php
/**
 * sfDateFormat class file.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the BSD License.
 *
 * Copyright(c) 2004 by Qiang Xue. All rights reserved.
 *
 * To contact the author write to {@link mailto:qiang.xue@gmail.com Qiang Xue}
 * The latest version of PRADO can be obtained from:
 * {@link http://prado.sourceforge.net/}
 *
 * @author     Wei Zhuo <weizhuo[at]gmail[dot]com>
 *
 * @version    $Id$
 */

/**
 * sfDateFormat class.
 *
 * The sfDateFormat class allows you to format dates and times with
 * predefined styles in a locale-sensitive manner. Formatting times
 * with the sfDateFormat class is similar to formatting dates.
 *
 * Formatting dates with the sfDateFormat class is a two-step process.
 * First, you create a formatter with the getDateInstance method.
 * Second, you invoke the format method, which returns a string containing
 * the formatted date.
 *
 * DateTime values are formatted using standard or custom patterns stored
 * in the properties of a DateTimeFormatInfo.
 *
 * @author Xiang Wei Zhuo <weizhuo[at]gmail[dot]com>
 *
 * @version v1.0, last update on Sat Dec 04 14:10:49 EST 2004
 */
class sfDateFormat
{
    /**
     * A list of tokens and their function call.
     *
     * @var array
     */
    protected $tokens = [
        'G' => 'Era',
        'y' => 'year',
        'M' => 'mon',
        'd' => 'mday',
        'h' => 'Hour12',
        'H' => 'hours',
        'm' => 'minutes',
        's' => 'seconds',
        'E' => 'wday',
        'D' => 'yday',
        'F' => 'DayInMonth',
        'w' => 'WeekInYear',
        'W' => 'WeekInMonth',
        'a' => 'AMPM',
        'k' => 'HourInDay',
        'K' => 'HourInAMPM',
        'z' => 'TimeZone',
    ];

    /**
     * A list of methods, to be used by the token function calls.
     *
     * @var array
     */
    protected $methods = [];

    /**
     * The sfDateTimeFormatInfo, containing culture specific patterns and names.
     *
     * @var sfDateTimeFormatInfo
     */
    protected $formatInfo;

    /**
     * Initializes a new sfDateFormat.
     *
     * @param mixed $formatInfo either, null, a sfCultureInfo instance, a DateTimeFormatInfo instance, or a locale
     *
     * @return sfDateFormat instance
     */
    public function __construct($formatInfo = null)
    {
        if (null === $formatInfo) {
            $this->formatInfo = sfDateTimeFormatInfo::getInvariantInfo();
        } elseif ($formatInfo instanceof sfCultureInfo) {
            $this->formatInfo = $formatInfo->DateTimeFormat;
        } elseif ($formatInfo instanceof sfDateTimeFormatInfo) {
            $this->formatInfo = $formatInfo;
        } else {
            $this->formatInfo = sfDateTimeFormatInfo::getInstance($formatInfo);
        }

        $this->methods = get_class_methods($this);
    }

    /**
     * Guesses a date without calling strtotime.
     *
     * @author Olivier Verdier <Olivier.Verdier@gmail.com>
     *
     * @param mixed  $time    the time as integer or string in strtotime format
     * @param string $pattern the input pattern; default is sql date or timestamp
     *
     * @return DateTime same array as the getdate function
     */
    public function getDate($time, $pattern = null)
    {
        if (null === $time) {
            return null;
        }
        if ($time instanceof sfOutputEscaper) {
            $time = $time->getRawValue();
        }
        if ($time instanceof DateTime) {
            return $time;
        }

        // if the type is not a php timestamp
        if ($isString = (string) $time !== (string) (int) $time) {
            if (!$pattern) {
                if (10 == strlen($time)) {
                    $pattern = 'i';
                } else { // otherwise, default:
                    $pattern = 'I';
                }
            }

            $pattern = $this->getPattern($pattern);
            $tokens = $this->getTokens($pattern);
            $pregPattern = '';
            $matchNames = [];
            // current regex allows any char at the end. avoids duplicating [^\d]+ pattern
            // this could cause issues with utf character width
            $allowsAllChars = true;
            foreach ($tokens as $token) {
                if ($matchName = $this->getFunctionName($token)) {
                    $allowsAllChars = false;
                    $pregPattern .= '(\d+)';
                    $matchNames[] = $matchName;
                } else {
                    if (!$allowsAllChars) {
                        $allowsAllChars = true;
                        $pregPattern .= '[^\d]+';
                    }
                }
            }
            preg_match('@'.$pregPattern.'@', $time, $matches);

            array_shift($matches);

            if (count($matchNames) == count($matches)) {
                $date = array_combine($matchNames, $matches);
                // guess the date if input with two digits
                if (2 == strlen($date['year'])) {
                    $date['year'] = date('Y', mktime(0, 0, 0, 1, 1, $date['year']));
                }
                $date = array_map('intval', $date);
            }
        }

        // the last attempt has failed we fall back on the default method
        if (!isset($date)) {
            if ($isString) {
                $numericalTime = @strtotime($time);
                if (false === $numericalTime) {
                    throw new sfException(sprintf('Impossible to parse date "%s" with format "%s".', $time, $pattern));
                }
            } else {
                $numericalTime = $time;
            }
        } else {
            // we set default values for the time
            foreach (['hours', 'minutes', 'seconds'] as $timeDiv) {
                if (!isset($date[$timeDiv])) {
                    $date[$timeDiv] = 0;
                }
            }
            $numericalTime = mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']);
        }
        $date = new DateTime();
        $date->setTimestamp($numericalTime);

        return $date;
    }

    /**
     * Formats a date according to the pattern.
     *
     * @param mixed  $time         the time as integer or string in strtotime format
     * @param string $pattern      the pattern
     * @param string $inputPattern the input pattern
     * @param string $charset      the charset
     *
     * @return string formatted date time
     */
    public function format($time, $pattern = 'F', $inputPattern = null, $charset = 'UTF-8')
    {
        $date = $this->getDate($time, $inputPattern);
        if (null === $pattern) {
            $pattern = 'F';
        }

        $pattern = $this->getPattern($pattern);
        $tokens = $this->getTokens($pattern);

        for ($i = 0, $max = count($tokens); $i < $max; ++$i) {
            $pattern = $tokens[$i];
            if ("'" == $pattern[0] && "'" == $pattern[strlen($pattern) - 1]) {
                $tokens[$i] = str_replace('``````', '\'', preg_replace('/(^\')|(\'$)/', '', $pattern));
            } elseif ('``````' == $pattern) {
                $tokens[$i] = '\'';
            } else {
                if (null !== $function = $this->getFunctionName($pattern)) {
                    $function = ucfirst($function);
                    $fName = 'get'.$function;
                    if (in_array($fName, $this->methods)) {
                        $tokens[$i] = $this->{$fName}($date, $pattern);
                    } else {
                        throw new sfException(sprintf('Function %s not found.', $function));
                    }
                }
            }
        }

        return sfToolkit::I18N_toEncoding(implode('', $tokens), $charset);
    }

    /**
     * Gets the pattern from DateTimeFormatInfo or some predefined patterns.
     * If the $pattern parameter is an array of 2 element, it will assume
     * that the first element is the date, and second the time
     * and try to find an appropriate pattern and apply
     * DateTimeFormatInfo::formatDateTime
     * See the tutorial documentation for futher details on the patterns.
     *
     * @param mixed $pattern a pattern
     *
     * @return string a pattern
     *
     * @see sfDateTimeFormatInfo::formatDateTime()
     */
    public function getPattern($pattern)
    {
        if (is_array($pattern) && 2 == count($pattern)) {
            return $this->formatInfo->formatDateTime($this->getPattern($pattern[0]), $this->getPattern($pattern[1]));
        }

        switch ($pattern) {
            case 'd':
                return $this->formatInfo->ShortDatePattern;

            case 'D':
                return $this->formatInfo->LongDatePattern;

            case 'p':
                return $this->formatInfo->MediumDatePattern;

            case 'P':
                return $this->formatInfo->FullDatePattern;

            case 't':
                return $this->formatInfo->ShortTimePattern;

            case 'T':
                return $this->formatInfo->LongTimePattern;

            case 'q':
                return $this->formatInfo->MediumTimePattern;

            case 'Q':
                return $this->formatInfo->FullTimePattern;

            case 'f':
                return $this->formatInfo->formatDateTime($this->formatInfo->LongDatePattern, $this->formatInfo->ShortTimePattern);

            case 'F':
                return $this->formatInfo->formatDateTime($this->formatInfo->LongDatePattern, $this->formatInfo->LongTimePattern);

            case 'g':
                return $this->formatInfo->formatDateTime($this->formatInfo->ShortDatePattern, $this->formatInfo->ShortTimePattern);

            case 'G':
                return $this->formatInfo->formatDateTime($this->formatInfo->ShortDatePattern, $this->formatInfo->LongTimePattern);

            case 'i':
                return 'yyyy-MM-dd';

            case 'I':
                return 'yyyy-MM-dd HH:mm:ss';

            case 'M':
            case 'm':
                return 'MMMM dd';

            case 'R':
            case 'r':
                return 'EEE, dd MMM yyyy HH:mm:ss';

            case 's':
                return 'yyyy-MM-ddTHH:mm:ss';

            case 'u':
                return 'yyyy-MM-dd HH:mm:ss z';

            case 'U':
                return 'EEEE dd MMMM yyyy HH:mm:ss';

            case 'Y':
            case 'y':
                return 'yyyy MMMM';

            default:
                return $pattern;
        }
    }

    /**
     * Returns an easy to parse input pattern
     * yy is replaced by yyyy and h by H.
     *
     * @param string $pattern pattern
     *
     * @return string input pattern
     */
    public function getInputPattern($pattern)
    {
        $pattern = $this->getPattern($pattern);

        $pattern = strtr($pattern, ['yyyy' => 'Y', 'h' => 'H', 'z' => '', 'a' => '']);
        $pattern = strtr($pattern, ['yy' => 'yyyy', 'Y' => 'yyyy']);

        return trim($pattern);
    }

    /**
     * For a particular token, get the corresponding function to call.
     *
     * @param string $token token
     *
     * @return mixed the function if good token, null otherwise
     */
    protected function getFunctionName($token)
    {
        if (isset($this->tokens[$token[0]])) {
            return $this->tokens[$token[0]];
        }
    }

    /**
     * Tokenizes the pattern. The tokens are delimited by group of
     * similar characters, e.g. 'aabb' will form 2 tokens of 'aa' and 'bb'.
     * Any substrings, starting and ending with a single quote (')
     * will be treated as a single token.
     *
     * @param string $pattern pattern
     *
     * @return array string tokens in an array
     */
    protected function getTokens($pattern)
    {
        $char = null;
        $tokens = [];
        $token = null;

        $text = false;

        for ($i = 0, $max = strlen($pattern); $i < $max; ++$i) {
            if (null == $char || $pattern[$i] == $char || $text) {
                $token .= $pattern[$i];
            } else {
                $tokens[] = str_replace("''", "'", $token);
                $token = $pattern[$i];
            }

            if ("'" == $pattern[$i] && false == $text) {
                $text = true;
            } elseif ($text && "'" == $pattern[$i] && "'" == $char) {
                $text = true;
            } elseif ($text && "'" != $char && "'" == $pattern[$i]) {
                $text = false;
            }

            $char = $pattern[$i];
        }
        $tokens[] = $token;

        return $tokens;
    }

    // makes a unix date from our incomplete $date array
    protected function getUnixDate($date)
    {
        return getdate($date->getTimestamp());
    }

    /**
     * Gets the year.
     * "yy" will return the last two digits of year.
     * "y", "yyy" and "yyyy" will return the full integer year.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string year
     */
    protected function getYear($date, $pattern = 'yyyy')
    {
        switch ($pattern) {
            case 'yy':
                return $date->format('y');

            case 'y':
            case 'yyy':
            case 'yyyy':
                return $date->format('Y');

            default:
                throw new sfException('The pattern for year is either "y", "yy", "yyy" or "yyyy".');
        }
    }

    /**
     * Gets the month.
     * "M" will return integer 1 through 12
     * "MM" will return integer 1 through 12 padded with 0 to two characters width
     * "MMM" will return the abrreviated month name, e.g. "Jan"
     * "MMMM" will return the month name, e.g. "January"
     * "MMMMM" will return the narrow month name, e.g. "J".
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string month name
     */
    protected function getMon($date, $pattern = 'M')
    {
        switch ($pattern) {
            case 'M':
                return $date->format('n');

            case 'MM':
                return $date->format('m');

            case 'MMM':
                return $this->formatInfo->AbbreviatedMonthNames[(int) $date->format('n') - 1];

            case 'MMMM':
                return $this->formatInfo->MonthNames[(int) $date->format('n') - 1];

            case 'MMMMM':
                return $this->formatInfo->NarrowMonthNames[(int) $date->format('n') - 1];

            default:
                throw new sfException('The pattern for month is "M", "MM", "MMM", "MMMM", "MMMMM".');
        }
    }

    /**
     * Gets the day of the week.
     * "E" will return integer 0 (for Sunday) through 6 (for Saturday).
     * "EE" will return the narrow day of the week, e.g. "M"
     * "EEE" will return the abrreviated day of the week, e.g. "Mon"
     * "EEEE" will return the day of the week, e.g. "Monday".
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string day of the week
     */
    protected function getWday($date, $pattern = 'EEEE')
    {
        $date = $this->getUnixDate($date);
        $day = $date['wday'];

        switch ($pattern) {
            case 'E':
                return $day;

            case 'EE':
                return $this->formatInfo->NarrowDayNames[$day];

            case 'EEE':
                return $this->formatInfo->AbbreviatedDayNames[$day];

            case 'EEEE':
                return $this->formatInfo->DayNames[$day];

            default:
                throw new sfException('The pattern for day of the week is "E", "EE", "EEE", or "EEEE".');
        }
    }

    /**
     * Gets the day of the month.
     * "d" for non-padding, "dd" will always return 2 characters.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string day of the month
     */
    protected function getMday($date, $pattern = 'd')
    {
        switch ($pattern) {
            case 'd':
                return $date->format('j');

            case 'dd':
                return $date->format('d');

            case 'dddd':
                return $this->getWday($date);

            default:
                throw new sfException('The pattern for day of the month is "d", "dd" or "dddd".');
        }
    }

    /**
     * Gets the era. i.e. in gregorian, year > 0 is AD, else BC.
     *
     * @todo How to support multiple Eras?, e.g. Japanese.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string era
     */
    protected function getEra($date, $pattern = 'G')
    {
        if ('G' != $pattern) {
            throw new sfException('The pattern for era is "G".');
        }

        return $this->formatInfo->getEra((int) $date->format('Y') > 0 ? 1 : 0);
    }

    /**
     * Gets the hours in 24 hour format, i.e. [0-23].
     * "H" for non-padding, "HH" will always return 2 characters.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string hours in 24 hour format
     */
    protected function getHours($date, $pattern = 'H')
    {
        switch ($pattern) {
            case 'H':
                return $date->format('G');

            case 'HH':
                return $date->format('H');

            default:
                throw new sfException('The pattern for 24 hour format is "H" or "HH".');
        }
    }

    /**
     * Get the AM/PM designator, 12 noon is PM, 12 midnight is AM.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string AM or PM designator
     */
    protected function getAMPM($date, $pattern = 'a')
    {
        if ('a' != $pattern) {
            throw new sfException('The pattern for AM/PM marker is "a".');
        }

        return $this->formatInfo->AMPMMarkers[(int) ((int) $date->format('G') / 12)];
    }

    /**
     * Gets the hours in 12 hour format.
     * "h" for non-padding, "hh" will always return 2 characters.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string hours in 12 hour format
     */
    protected function getHour12($date, $pattern = 'h')
    {
        switch ($pattern) {
            case 'h':
                return $date->format('g');

            case 'hh':
                return $date->format('h');

            default:
                throw new sfException('The pattern for 24 hour format is "H" or "HH".');
        }
    }

    /**
     * Gets the minutes.
     * "m" for non-padding, "mm" will always return 2 characters.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string minutes
     */
    protected function getMinutes($date, $pattern = 'm')
    {
        switch ($pattern) {
            case 'm':
                return (string) (int) $date->format('i');

            case 'mm':
                return $date->format('i');

            default:
                throw new sfException('The pattern for minutes is "m" or "mm".');
        }
    }

    /**
     * Gets the seconds.
     * "s" for non-padding, "ss" will always return 2 characters.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string seconds
     */
    protected function getSeconds($date, $pattern = 's')
    {
        switch ($pattern) {
            case 's':
                return (string) (int) $date->format('s');

            case 'ss':
                return $date->format('s');

            default:
                throw new sfException('The pattern for seconds is "s" or "ss".');
        }
    }

    /**
     * Gets the timezone from the server machine.
     *
     * @todo How to get the timezone for a different region?
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return string time zone
     */
    protected function getTimeZone($date, $pattern = 'z')
    {
        // mapping to PHP pattern symbols
        switch ($pattern) {
            case 'z':
                $pattern = 'T';
                break;

            case 'Z':
                $pattern = 'O';
                break;

            default:
                throw new sfException('The pattern for time zone is "z" or "Z".');
        }

        return $date->format($pattern);
    }

    /**
     * Gets the day in the year, e.g. [1-366].
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int hours in AM/PM format
     */
    protected function getYday($date, $pattern = 'D')
    {
        if ('D' != $pattern) {
            throw new sfException('The pattern for day in year is "D".');
        }

        throw new sfException('Not implemented.');
    }

    /**
     * Gets day in the month.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int day in month
     */
    protected function getDayInMonth($date, $pattern = 'FF')
    {
        switch ($pattern) {
            case 'F':
                return $date->format('j');

            case 'FF':
                return $date->format('d');

            default:
                throw new sfException('The pattern for day in month is "F" or "FF".');
        }
    }

    /**
     * Gets the week in the year.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int week in year
     */
    protected function getWeekInYear($date, $pattern = 'w')
    {
        if ('w' != $pattern) {
            throw new sfException('The pattern for week in year is "w".');
        }

        return $date->format('W');
    }

    /**
     * Gets week in the month.
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int week in month
     */
    protected function getWeekInMonth($date, $pattern = 'W')
    {
        if ('W' != $pattern) {
            throw new sfException('The pattern for week in month is "W".');
        }
        $firstDate = clone $date;
        $firstDate->modify(sprintf('-%d days', (int) $date->format('j') - 1));

        return (int) $date->format('W') - $firstDate->format('W');
    }

    /**
     * Gets the hours [1-24].
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int hours [1-24]
     */
    protected function getHourInDay($date, $pattern = 'k')
    {
        if ('k' != $pattern) {
            throw new sfException('The pattern for hour in day is "k".');
        }

        return (int) $date->format('G') + 1;
    }

    /**
     * Gets the hours in AM/PM format, e.g [1-12].
     *
     * @param DateTime $date    getdate format
     * @param string   $pattern a pattern
     *
     * @return int hours in AM/PM format
     */
    protected function getHourInAMPM($date, $pattern = 'K')
    {
        if ('K' != $pattern) {
            throw new sfException('The pattern for hour in AM/PM is "K".');
        }

        return ((int) $date->format('G') + 1) % 12;
    }
}
