<?php
/*
 * potter
 *
 * Copyright (c) 2018 Christoph Fischer, https://christoph-fischer.org
 * Author: Christoph Fischer, chris@toph.de
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Peregrinus\Potter;

class PotterEngine
{

    const CRLF = "\r\n";
    const POTTER_VERSION = '1.0';

    protected $keys = [];
    protected $regularExpression = '/{{function}}\((?: *)([\"\'](?:(?<=\")[^\"\\\\]*(?s:\\\\.[^\"\\\\]*)*\"'
    . '|(?<=\')[^\'\\\\]*(?s:\\\\.[^\'\\\\]*)*\'))/m';
    protected $pluginName = '';
    protected $pluginData = [];
    protected $pot = '';

    protected $functions = [
        '__',
        '_e',
        'esc_html_e',
        'esc_html__',
        '_x',
        '_ex',
        'esc_attr_x',
        'esc_html_x'
    ];

    /**
     * Output text to console, followed by line feed
     * @param string $text Text to display
     */
    protected function console($text)
    {
        echo $text . self::CRLF;
    }


    /**
     * Retrieve metadata from a file.
     *
     * Searches for metadata in the first 8kiB of a file, such as a plugin or theme.
     * Each piece of metadata must be on its own line. Fields can not span multiple
     * lines, the value will get cut at the end of the first line.
     *
     * If the file data is not within that first 8kiB, then the author should correct
     * their plugin file and move the data headers to the top.
     *
     * This function is derrived from Wordpress code. All appropriate license terms
     * apply.
     * @source https://github.com/WordPress/WordPress/blob/fdd5b8dacdf5f3e107747e9870c2f2db3f3481b1/wp-includes/functions.php#L5111
     *
     * @link https://codex.wordpress.org/File_Header
     *
     * @since 2.9.0
     *
     * @param string $file Absolute path to the file.
     * @return array Array of file headers in `HeaderKey => Header Value` format.
     */
    protected function getPluginMetadata($file)
    {
        $allHeaders = [
            'Name' => 'Plugin Name',
            'PluginURI' => 'Plugin URI',
            'Version' => 'Version',
            'Description' => 'Description',
            'Author' => 'Author',
            'AuthorURI' => 'Author URI',
            'TextDomain' => 'Text Domain',
            'DomainPath' => 'Domain Path',
        ];

        // We don't need to write to the file, so just open for reading.
        $handle = fopen($file, 'r');
        // Pull only the first 8kiB of the file in.
        $fileData = fread($handle, 8192);
        // PHP will close file handle, but we are good citizens.
        fclose($handle);
        // Make sure we catch CR-only line endings.
        $fileData = str_replace("\r", "\n", $fileData);
        foreach ($allHeaders as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $fileData, $match) && $match[1]) {
                $allHeaders[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
            } else {
                $allHeaders[$field] = '';
            }
        }
        return $allHeaders;
    }

    protected function checkWPPlugin()
    {
        $this->pluginName = basename(getcwd());
        if (!file_exists($this->pluginName . '.php')) {
            die('Not a wordpress plugin');
        } else {
            $this->console('Found WordPress plugin ' . $this->pluginName);
            $this->pluginData = $this->getPluginMetadata($this->pluginName . '.php');
            print_r($this->pluginData);
        }
        $this->pot = realpath(getcwd() . $this->pluginData['DomainPath'] . $this->pluginData['TextDomain'] . '.pot');
        $this->console('Target file is ' . $this->pot);

        if (file_exists($this->pot)) {
            copy($this->pot, $this->pot . '.bak');
            $this->console('Backup of existing file created as ' . $this->pot . '.bak');
        }
    }

    protected function doFile($file)
    {
        echo $file . "\r\n";
        $lineCtr = 0;

        foreach (explode("\n", str_replace("\r", '', file_get_contents($file))) as $line) {
            $lineCtr++;
            foreach ($this->functions as $function) {
                if (preg_match_all(str_replace('{{function}}', $function, $this->regularExpression), $line, $matches)) {
                    foreach ($matches[1] as $match) {
                        $match = substr($match, 1, -1);
                        $this->keys[$match][] = ['file' => $file, 'line' => $lineCtr];
                    }
                }
            }
        }
    }

    public function runFolder($folder = './')
    {

        if (substr($folder, -1) !== '/') {
            $folder .= '/';
        }
        echo $folder . "\r\n";
        foreach (glob($folder . '*') as $file) {
            if (is_dir($file)) {
                $this->runFolder($file);
            } elseif (pathinfo($file, PATHINFO_EXTENSION) == 'php') {
                $this->doFile($file);
            }
        }
    }

    public function run()
    {
        $this->checkWPPlugin();
        $this->runFolder();
        $this->createPot();
    }

    protected function createPot()
    {
        $header = '#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: ' . $this->pluginData['Name'] . ' ' . $this->pluginData['Version'] . '\n"
"Report-Msgid-Bugs-To: ' . $this->pluginData['PluginURI'] . '\n"
"POT-Creation-Date: ' . strftime('%Y-%m-%d %H:%M%z') . '\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Generator: Potter ' . self::POTTER_VERSION . '"

# THIS FILE IS AUTOCREATED BY POTTER
# All changes will be lost with the next run of Potter!

';


        $output = $header . self::CRLF . self::CRLF;
        foreach ($this->keys as $text => $occurences) {
            foreach ($occurences as $occurence) {
                $output .= '#: ' . $occurence['file'] . ':' . $occurence['line'] . self::CRLF;
            }
            $output .= 'msgid "' . $text . '"' . self::CRLF;
            $output .= 'msgstr ""' . self::CRLF . self::CRLF;
        }
        file_put_contents($this->pot, $output);
        $this->console(count($this->keys) . ' strings exported to ' . $this->pot);
    }
}
