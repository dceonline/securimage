<?php
error_reporting(E_ALL); ini_set('display_errors', 'on');
/**
 * Project:     Securimage: A PHP class for creating and managing form CAPTCHA images<br />
 * File:        securimage.php<br />
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or any later version.<br /><br />
 *
 * This library is distributed in the hope that it will be useful,scr
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.<br /><br />
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA<br /><br />
 *
 * Any modifications to the library should be indicated clearly in the source code
 * to inform users that the changes are not a part of the original software.<br /><br />
 *
 * If you found this script useful, please take a quick moment to rate it.<br />
 * http://www.hotscripts.com/rate/49400.html  Thanks.
 *
 * @link http://www.phpcaptcha.org Securimage PHP CAPTCHA
 * @link http://www.phpcaptcha.org/latest.zip Download Latest Version
 * @link http://www.phpcaptcha.org/Securimage_Docs/ Online Documentation
 * @copyright 2009 Drew Phillips
 * @author Drew Phillips <drew@drew-phillips.com>
 * @version 3.0 (March 2011)
 * @package Securimage
 *
 */

/**
 ChangeLog
 
 3.0
 - Convert to PHP5
 - Remove support for GD fonts, require FreeType
 - Remove support for multi-color codes
 - Add option to make codes case-sensitive
 - Add namespaces to support multiple captchas on a single page or page specific captchas
 - A option to show simple math problems instead of codes

 2.0.2
 - Fix pathing to make integration into libraries easier (Nathan Phillip Brink ohnobinki@ohnopublishing.net)

 2.0.1
 - Add support for browsers with cookies disabled (requires php5, sqlite) maps users to md5 hashed ip addresses and md5 hashed codes for security
 - Add fallback to gd fonts if ttf support is not enabled or font file not found (Mike Challis http://www.642weather.com/weather/scripts.php)
 - Check for previous definition of image type constants (Mike Challis)
 - Fix mime type settings for audio output
 - Fixed color allocation issues with multiple colors and background images, consolidate allocation to one function
 - Ability to let codes expire after a given length of time
 - Allow HTML color codes to be passed to Securimage_Color (suggested by Mike Challis)

 2.0.0
 - Add mathematical distortion to characters (using code from HKCaptcha)
 - Improved session support
 - Added Securimage_Color class for easier color definitions
 - Add distortion to audio output to prevent binary comparison attack (proposed by Sven "SavageTiger" Hagemann [insecurity.nl])
 - Flash button to stream mp3 audio (Douglas Walsh www.douglaswalsh.net)
 - Audio output is mp3 format by default
 - Change font to AlteHaasGrotesk by yann le coroller
 - Some code cleanup 

 1.0.4 (unreleased)
 - Ability to output audible codes in mp3 format to stream from flash

 1.0.3.1
 - Error reading from wordlist in some cases caused words to be cut off 1 letter short

 1.0.3
 - Removed shadow_text from code which could cause an undefined property error due to removal from previous version

 1.0.2
 - Audible CAPTCHA Code wav files
 - Create codes from a word list instead of random strings

 1.0
 - Added the ability to use a selected character set, rather than a-z0-9 only.
 - Added the multi-color text option to use different colors for each letter.
 - Switched to automatic session handling instead of using files for code storage
 - Added GD Font support if ttf support is not available.  Can use internal GD fonts or load new ones.
 - Added the ability to set line thickness
 - Added option for drawing arced lines over letters
 - Added ability to choose image type for output

 */


/**
 * Securimage CAPTCHA Class.
 *
 * @version    3.0
 * @package    Securimage
 * @subpackage classes
 * @author     Drew Phillips <drew@drew-phillips.com>
 *
 */
class Securimage
{
    const SI_IMAGE_JPEG = 1;
    const SI_IMAGE_PNG  = 2;
    const SI_IMAGE_GIF  = 3;
    
    const SI_CAPTCHA_STRING     = 0;
    const SI_CAPTCHA_MATHEMATIC = 1;
    
    public $image_width  = 200;
    public $image_height = 80;
    public $image_type   = self::SI_IMAGE_PNG;

    public $image_bg_color = '#ffffff';
    public $text_color     = '#707070';
    public $line_color     = '#707070';
    
    public $text_transparency_percentage = 50;
    public $use_transparent_text         = false;
    
    public $code_length    = 6;
    public $case_sensitive = false;
    public $charset        = 'ABCDEFGHKLMNPRSTUVWYZabcdefghklmnprstuvwyz23456789';
    public $expiry_time    = 900;
    
    public $session_name   = null;
    
    public $use_wordlist   = false;

    
    public $perturbation = 0.75;
    public $num_lines    = 4;
    public $noise_level  = 0;
    
    public $image_signature = '';
    public $signature_color = '#707070';
    
    public $use_sqlite_db = false;
    public $captcha_type  = self::SI_CAPTCHA_STRING;
    
    public $namespace;
    public $ttf_file;
    public $wordlist_file;
    public $background_directory;
    public $sqlite_database;
    public $audio_path;

    
    protected $im;
    protected $tmpimg;
    protected $bgimg;
    protected $iscale = 5;
    
    protected $securimage_path = null;
    
    protected $code;
    protected $code_display;
    
    protected $captcha_code;
    protected $sqlite_handle;
    
    protected $gdbgcolor;
    protected $gdtextcolor;
    protected $gdlinecolor;
    protected $gdsignaturecolor;
    
    public function __construct()
    {
        $this->securimage_path = dirname(__FILE__);

        $this->image_bg_color  = $this->initColor($this->image_bg_color,  '#ffffff');
        $this->text_color      = $this->initColor($this->text_color,      '#616161');
        $this->line_color      = $this->initColor($this->line_color,      '#616161');
        $this->signature_color = $this->initColor($this->signature_color, '#616161');

        if ($this->ttf_file == null) {
            $this->ttf_file = $this->securimage_path . '/AHGBold.ttf';
        }
        
        if ($this->wordlist_file == null) {
            $this->wordlist_file = $this->securimage_path . '/words/words.txt';
        }
        
        if ($this->sqlite_database == null) {
            $this->sqlite_database = $this->securimage_path . '/database/securimage.sqlite';
        }
        
        if ($this->audio_path == null) {
            $this->audio_path = $this->securimage_path . '/audio/';
        }
        
        if ($this->code_length == null || $this->code_length < 1) {
            $this->code_length = 6;
        }
        
        if ($this->perturbation == null || !is_numeric($this->perturbation)) {
            $this->perturbation = 0.75;
        }
        
        if ($this->namespace == null || !is_string($this->namespace)) {
            $this->namespace = 'default';
        }

        // Initialize session or attach to existing
        if ( session_id() == '' ) { // no session has been started yet, which is needed for validation
            if ($this->session_name == null || trim($this->session_name) != '') {
                session_name(trim($this->session_name)); // set session name if provided
            }
            session_start();
        }
    }
    
    public function show($background_image = '')
    {
        if($background_image != '' && is_readable($background_image)) {
            $this->bgimg = $background_image;
        }

        $this->doImage();
    }
    
    public function check($code)
    {
        $this->code_entered = $code;
        $this->validate();
        return $this->correct_code;
    }
    
    public function outputAudioFile()
    {
        $ext = 'wav';
        
        header("Content-Disposition: attachment; filename=\"securimage_audio.{$ext}\"");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: Sun, 1 Jan 2000 12:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');
        header('Content-type: audio/x-wav');
        
        $audio = $this->getAudibleCode($ext);

        header('Content-Length: ' . strlen($audio));

        echo $audio;
        exit;
    }
    
    protected function doImage()
    {
        if( ($this->use_transparent_text == true || $this->bgimg != '') && function_exists('imagecreatetruecolor')) {
            $imagecreate = 'imagecreatetruecolor';
        } else {
            $imagecreate = 'imagecreate';
        }
        
        $this->im     = $imagecreate($this->image_width, $this->image_height);
        $this->tmpimg = $imagecreate($this->image_width * $this->iscale, $this->image_height * $this->iscale);
        
        $this->allocateColors();
        imagepalettecopy($this->tmpimg, $this->im);

        $this->setBackground();

        $this->createCode();

        if ($this->noise_level > 0) {
            $this->drawNoise();
        }
        
        $this->drawWord();
        
        if ($this->perturbation > 0 && is_readable($this->ttf_file)) {
            $this->distortedCopy();
        }

        if ($this->num_lines > 0) {
            $this->drawLines();
        }

        if (trim($this->image_signature) != '') {
            $this->addSignature();
        }

        $this->output();
    }
    
    protected function allocateColors()
    {
        // allocate bg color first for imagecreate
        $this->gdbgcolor = imagecolorallocate($this->im,
                                              $this->image_bg_color->r,
                                              $this->image_bg_color->g,
                                              $this->image_bg_color->b);
        
        $alpha = intval($this->text_transparency_percentage / 100 * 127);
        
        if ($this->use_transparent_text == true) {
            $this->gdtextcolor = imagecolorallocatealpha($this->im,
                                                         $this->text_color->r,
                                                         $this->text_color->g,
                                                         $this->text_color->b,
                                                         $alpha);
            $this->gdlinecolor = imagecolorallocatealpha($this->im,
                                                         $this->line_color->r,
                                                         $this->line_color->g,
                                                         $this->line_color->b,
                                                         $alpha);
        } else {
            $this->gdtextcolor = imagecolorallocate($this->im,
                                                    $this->text_color->r,
                                                    $this->text_color->g,
                                                    $this->text_color->b);
            $this->gdlinecolor = imagecolorallocate($this->im,
                                                    $this->line_color->r,
                                                    $this->line_color->g,
                                                    $this->line_color->b);
        }
    
        $this->gdsignaturecolor = imagecolorallocate($this->im,
                                                     $this->signature_color->r,
                                                     $this->signature_color->g,
                                                     $this->signature_color->b);

    }
    
    protected function setBackground()
    {
        // set background color of image by drawing a rectangle since imagecreatetruecolor doesn't set a bg color
        imagefilledrectangle($this->im, 0, 0,
                             $this->image_width, $this->image_height,
                             $this->gdbgcolor);
        imagefilledrectangle($this->tmpimg, 0, 0,
                             $this->image_width * $this->iscale, $this->image_height * $this->iscale,
                             $this->gdbgcolor);
    
        if ($this->bgimg == '') {
            if ($this->background_directory != null && 
                is_dir($this->background_directory) &&
                is_readable($this->background_directory))
            {
                $img = $this->getBackgroundFromDirectory();
                if ($img != false) {
                    $this->bgimg = $img;
                }
            }
        }
        
        if ($this->bgimg == '') {
            return;
        }

        $dat = @getimagesize($this->bgimg);
        if($dat == false) { 
            return;
        }

        switch($dat[2]) {
            case 1:  $newim = @imagecreatefromgif($this->bgimg); break;
            case 2:  $newim = @imagecreatefromjpeg($this->bgimg); break;
            case 3:  $newim = @imagecreatefrompng($this->bgimg); break;
            default: return;
        }

        if(!$newim) return;

        imagecopyresized($this->im, $newim, 0, 0, 0, 0,
                         $this->image_width, $this->image_height,
                         imagesx($newim), imagesy($newim));
    }
    
    protected function getBackgroundFromDirectory()
    {
        $images = array();

        if ($dh = opendir($this->background_directory)) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match('/(jpg|gif|png)$/i', $file)) $images[] = $file;
            }

            closedir($dh);

            if (sizeof($images) > 0) {
                return rtrim($this->background_directory, '/') . '/' . $images[rand(0, sizeof($images)-1)];
            }
        }

        return false;
    }
    
    protected function createCode()
    {
        $this->code = false;

        switch($this->captcha_type) {
            case self::SI_CAPTCHA_MATHEMATIC:
            {
                $signs = array('+', '-', 'x');
                $left  = rand(1, 10);
                $right = rand(1, 5);
                $sign  = $signs[rand(0, 2)];
                
                switch($sign) {
                    case 'x': $c = $left * $right; break;
                    case '-': $c = $left - $right; break;
                    default:  $c = $left + $right; break;
                }
                
                $this->code         = $c;
                $this->code_display = "$left $sign $right";
                break;
            }
            
            default:
            {
                if ($this->use_wordlist && is_readable($this->wordlist_file)) {
                    $this->code = $this->readCodeFromFile();
                }

                if ($this->code == false) {
                    $this->code = $this->generateCode($this->code_length);
                }
                
                $this->code_display = $this->code;
                $this->code         = ($this->case_sensitive) ? $this->code : strtolower($this->code);
            } // default
        }
        
        $this->saveData();
    }
    
    protected function drawWord()
    {
        $width2  = $this->image_width * $this->iscale;
        $height2 = $this->image_height * $this->iscale;
         
        if (!is_readable($this->ttf_file)) {
            imagestring($this->im, 4, 10, ($this->image_height / 2) - 5, 'Failed to load TTF font file!', $this->gdtextcolor);
        } else {
            if ($this->perturbation > 0) {
                $font_size = $height2 * .4;
                $bb = imageftbbox($font_size, 0, $this->ttf_file, $this->code_display);
                $tx = $bb[4] - $bb[0];
                $ty = $bb[5] - $bb[1];
                $x  = floor($width2 / 2 - $tx / 2 - $bb[0]);
                $y  = round($height2 / 2 - $ty / 2 - $bb[1]);

                imagettftext($this->tmpimg, $font_size, 0, $x, $y, $this->gdtextcolor, $this->ttf_file, $this->code_display);
            } else {
                $font_size = $this->image_height * .4;
                $bb = imageftbbox($font_size, 0, $this->ttf_file, $this->code_display);
                $tx = $bb[4] - $bb[0];
                $ty = $bb[5] - $bb[1];
                $x  = floor($this->image_width / 2 - $tx / 2 - $bb[0]);
                $y  = round($this->image_height / 2 - $ty / 2 - $bb[1]);

                imagettftext($this->im, $font_size, 0, $x, $y, $this->gdtextcolor, $this->ttf_file, $this->code_display);
            }
        }
        
        // DEBUG
        //$this->im = $this->tmpimg;
        //$this->output();
        
    }
    protected function distortedCopy()
    {
        $numpoles = 3; // distortion factor
        // make array of poles AKA attractor points
        for ($i = 0; $i < $numpoles; ++ $i) {
            $px[$i]  = rand($this->image_width  * 0.2, $this->image_width  * 0.8);
            $py[$i]  = rand($this->image_height * 0.2, $this->image_height * 0.8);
            $rad[$i] = rand($this->image_height * 0.2, $this->image_height * 0.8);
            $tmp     = ((- $this->frand()) * 0.15) - .15;
            $amp[$i] = $this->perturbation * $tmp;
        }
        
        $bgCol = imagecolorat($this->tmpimg, 0, 0);
        $width2 = $this->iscale * $this->image_width;
        $height2 = $this->iscale * $this->image_height;
        imagepalettecopy($this->im, $this->tmpimg); // copy palette to final image so text colors come across
        // loop over $img pixels, take pixels from $tmpimg with distortion field
        for ($ix = 0; $ix < $this->image_width; ++ $ix) {
            for ($iy = 0; $iy < $this->image_height; ++ $iy) {
                $x = $ix;
                $y = $iy;
                for ($i = 0; $i < $numpoles; ++ $i) {
                    $dx = $ix - $px[$i];
                    $dy = $iy - $py[$i];
                    if ($dx == 0 && $dy == 0) {
                        continue;
                    }
                    $r = sqrt($dx * $dx + $dy * $dy);
                    if ($r > $rad[$i]) {
                        continue;
                    }
                    $rscale = $amp[$i] * sin(3.14 * $r / $rad[$i]);
                    $x += $dx * $rscale;
                    $y += $dy * $rscale;
                }
                $c = $bgCol;
                $x *= $this->iscale;
                $y *= $this->iscale;
                if ($x >= 0 && $x < $width2 && $y >= 0 && $y < $height2) {
                    $c = imagecolorat($this->tmpimg, $x, $y);
                }
                if ($c != $bgCol) { // only copy pixels of letters to preserve any background image
                    imagesetpixel($this->im, $ix, $iy, $c);
                }
            }
        }
    }
    
    protected function drawLines()
    {
        for ($line = 0; $line < $this->num_lines; ++ $line) {
            $x = $this->image_width * (1 + $line) / ($this->num_lines + 1);
            $x += (0.5 - $this->frand()) * $this->image_width / $this->num_lines;
            $y = rand($this->image_height * 0.1, $this->image_height * 0.9);
            
            $theta = ($this->frand() - 0.5) * M_PI * 0.7;
            $w = $this->image_width;
            $len = rand($w * 0.4, $w * 0.7);
            $lwid = rand(0, 2);
            
            $k = $this->frand() * 0.6 + 0.2;
            $k = $k * $k * 0.5;
            $phi = $this->frand() * 6.28;
            $step = 0.5;
            $dx = $step * cos($theta);
            $dy = $step * sin($theta);
            $n = $len / $step;
            $amp = 1.5 * $this->frand() / ($k + 5.0 / $len);
            $x0 = $x - 0.5 * $len * cos($theta);
            $y0 = $y - 0.5 * $len * sin($theta);
            
            $ldx = round(- $dy * $lwid);
            $ldy = round($dx * $lwid);
            
            for ($i = 0; $i < $n; ++ $i) {
                $x = $x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi);
                $y = $y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi);
                imagefilledrectangle($this->im, $x, $y, $x + $lwid, $y + $lwid, $this->gdlinecolor);
            }
        }
    }
    
    protected function drawNoise()
    {
        if ($this->noise_level > 10) {
            $noise_level = 10;
        } else {
            $noise_level = $this->noise_level;
        }

        $t0 = microtime(true);
        
        $noise_level *= 125; // an arbitrary number that works well on a 1-10 scale
        
        $points = $this->image_width * $this->image_height * $this->iscale;
        $height = $this->image_height * $this->iscale;
        $width  = $this->image_width * $this->iscale;
        for ($i = 0; $i < $noise_level; ++$i) {
            $x = rand(10, $width);
            $y = rand(10, $height);
            $size = rand(7, 10);
            if ($x - $size <= 0 && $y - $size <= 0) continue; // dont cover 0,0 since it is used by imagedistortedcopy
            imagefilledarc($this->tmpimg, $x, $y, $size, $size, 0, 360, $this->gdlinecolor, IMG_ARC_PIE);
        }
        
        $t1 = microtime(true);
        
        $t = $t1 - $t0;
        
        /*
        // DEBUG
        imagestring($this->tmpimg, 5, 25, 30, "$t", $this->gdlinecolor);
        header('content-type: image/png');
        imagepng($this->tmpimg);
        exit;
        */
    }
    
    protected function output()
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        
        switch ($this->image_type) {
            case self::SI_IMAGE_JPEG:
                header("Content-Type: image/jpeg");
                imagejpeg($this->im, null, 90);
                break;
            case self::SI_IMAGE_GIF:
                header("Content-Type: image/gif");
                imagegif($this->im);
                break;
            default:
                header("Content-Type: image/png");
                imagepng($this->im);
                break;
        }
        
        imagedestroy($this->im);
        exit();
    }
    
    protected function getAudibleCode($format = 'wav')
    {
        // override any format other than wav for now
        // this is due to security issues with MP3 files
        // now even wav files are probably vulnerable
        // 
        $format  = 'wav';
        
        $letters = array();
        $code    = $this->getCode();

        if ($code == '') {
            $this->createCode();
            $code = $this->getCode();
        }

        for($i = 0; $i < strlen($code); ++$i) {
            $letters[] = $code{$i};
        }
        
        if ($format == 'mp3') {
            return $this->generateMP3($letters);
        } else {
            return $this->generateWAV($letters);
        }
    }

    
    protected function readCodeFromFile()
    {
        $fp = @fopen($this->wordlist_file, 'rb');
        if (!$fp) return false;

        $fsize = filesize($this->wordlist_file);
        if ($fsize < 128) return false; // too small of a list to be effective

        fseek($fp, rand(0, $fsize - 16), SEEK_SET); // seek to a random position of file from 0 to filesize-16
        $data = fread($fp, 64); // read a chunk from our random position
        fclose($fp);
        $data = preg_replace("/\r?\n/", "\n", $data);

        $start = @strpos($data, "\n", rand(0, 56)) + 1; // random start position
        $end   = @strpos($data, "\n", $start);          // find end of word
        
        if ($start === false) {
            return false;
        } else if ($end === false) {
            $end = strlen($data);
        }

        return strtolower(substr($data, $start, $end - $start)); // return a line of the file
    }
    
    protected function generateCode()
    {
        $code = '';

        for($i = 1, $cslen = strlen($this->charset); $i <= $this->code_length; ++$i) {
            $code .= $this->charset{rand(0, $cslen - 1)};
        }
        return $code;
    }
    
    protected function validate()
    {
        $code = $this->getCode();
        // returns stored code, or an empty string if no stored code was found
        // checks the session and sqlite database if enabled
        
        $code_entered = trim( (($this->case_sensitive) ? $this->code_entered
                                                       : strtolower($this->code_entered))
                        );
        $this->correct_code = false;

        if ($code != '') {
            if ($code == $code_entered) {
                $this->correct_code = true;
                $_SESSION['securimage_code_value'][$this->namespace] = '';
                $_SESSION['securimage_code_ctime'][$this->namespace] = '';
                $this->clearCodeFromDatabase();
            }
        }
    }
    
    protected function getCode()
    {
        $code = '';
        
        if (isset($_SESSION['securimage_code_value'][$this->namespace]) &&
         trim($_SESSION['securimage_code_value'][$this->namespace]) != '') {
            if ($this->isCodeExpired(
            $_SESSION['securimage_code_ctime'][$this->namespace]) == false) {
                $code = $_SESSION['securimage_code_value'][$this->namespace];
            }
        } else if ($this->use_sqlite_db == true && function_exists('sqlite_open')) {
            // no code in session - may mean user has cookies turned off
            $this->openDatabase();
            $code = $this->getCodeFromDatabase();
        } else { /* no code stored in session or sqlite database, validation will fail */ }
        
        return $code;
    }
    
    protected function saveData()
    {
        $_SESSION['securimage_code_value'][$this->namespace] = $this->code;
        $_SESSION['securimage_code_ctime'][$this->namespace] = time();
        
        $this->saveCodeToDatabase();
    }
    
    protected function saveCodeToDatabase()
    {
        $success = false;
        
        $this->openDatabase();
        
        if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
            $ip      = $_SERVER['REMOTE_ADDR'];
            $time    = time();
            $code    = $_SESSION['securimage_code_value'][$this->namespace]; // if cookies are disabled the session still exists at this point
            $success = sqlite_query($this->sqlite_handle,
                                    "INSERT OR REPLACE INTO codes(ip, code, namespace, created)
                                    VALUES('$ip', '$code', '{$this->namespace}', $time)");
        }
        
        return $success !== false;
    }
    
    protected function openDatabase()
    {
        $this->sqlite_handle = false;
        
        if ($this->use_sqlite_db && function_exists('sqlite_open')) {
            $this->sqlite_handle = sqlite_open($this->sqlite_database, 0666, $error);
            
            if ($this->sqlite_handle !== false) {
                $res = sqlite_query($this->sqlite_handle, "PRAGMA table_info(codes)");
                if (sqlite_num_rows($res) == 0) {
                    sqlite_query($this->sqlite_handle, "CREATE TABLE codes (ip VARCHAR(32) PRIMARY KEY, code VARCHAR(32) NOT NULL, namespace VARCHAR(32) NOT NULL, created INTEGER)");
                }
            }
            
            return $this->sqlite_handle != false;
        }
        
        return $this->sqlite_handle;
    }
    
    protected function getCodeFromDatabase()
    {
        $code = '';

        if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $ns = sqlite_escape_string($this->namespace);

            $res = sqlite_query($this->sqlite_handle, "SELECT * FROM codes WHERE ip = '$ip' AND namespace = '$ns'");
            if ($res && sqlite_num_rows($res) > 0) {
                $res = sqlite_fetch_array($res);

                if ($this->isCodeExpired($res['created']) == false) {
                    $code = $res['code'];
                }
            }
        }
        return $code;
    }
    
    protected function clearCodeFromDatabase()
    {
        if (is_resource($this->sqlite_handle)) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $ns = sqlite_escape_string($this->namespace);
            
            sqlite_query($this->sqlite_handle, "DELETE FROM codes WHERE ip = '$ip' AND namespace = '$ns'");
        }
    }
    
    protected function purgeOldCodesFromDatabase()
    {
        if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
            $now   = time();
            $limit = (!is_numeric($this->expiry_time) || $this->expiry_time < 1) ? 86400 : $this->expiry_time;
            
            sqlite_query($this->sqlite_handle, "DELETE FROM codes WHERE $now - created > $limit");
        }
    }
    
    protected function isCodeExpired($creation_time)
    {
        $expired = true;
        
        if (!is_numeric($this->expiry_time) || $this->expiry_time < 1) {
            $expired = false;
        } else if (time() - $creation_time < $this->expiry_time) {
            $expired = false;
        }
        
        return $expired;
    }
    
    /**
     * 
     * Generate an MP3 audio file of the captcha image
     * 
     * @deprecated 3.0
     */
    protected function generateMP3()
    {
        return false;
    }
    
    protected function generateWAV($letters)
    {
        $data_len       = 0;
        $files          = array();
        $out_data       = '';
        $out_channels   = 0;
        $out_samplert   = 0;
        $out_bpersample = 0;
        $numSamples     = 0;

        for ($i = 0; $i < sizeof($letters); ++$i) {
            $letter   = $letters[$i];
            $filename = $this->audio_path . strtoupper($letter) . '.wav';
            $file     = array();
            $data     = @file_get_contents($filename);
            
            if ($data === false) {
                echo 'read failed';
                var_dump($filename);
                var_dump($letters);
                var_dump($letter);
                return false;
            }

            $header = substr($data, 0, 36);
            $info   = unpack('NChunkID/VChunkSize/NFormat/NSubChunk1ID/'
                            .'VSubChunk1Size/vAudioFormat/vNumChannels/'
                            .'VSampleRate/VByteRate/vBlockAlign/vBitsPerSample',
                             $header);
            
            $dataPos        = strpos($data, 'data');
            $out_channels   = $info['NumChannels'];
            $out_samplert   = $info['SampleRate'];
            $out_bpersample = $info['BitsPerSample'];
            
            if ($dataPos === false) {
                // wav file with no data?
                echo 'no data';
                return false;
            }
            
            if ($info['AudioFormat'] != 1) {
                // only work with PCM audio
                echo 'not pcm';
                return false;
            }
            
            if ($info['SubChunk1Size'] != 16 && $info['SubChunk1Size'] != 18) {
                // probably unsupported extension
                echo 'bad size';
                var_dump($info['SubChunk1Size']);
                return false;
            }
            
            if ($info['SubChunk1Size'] > 16) {
                $header .= substr($data, 36, $info['SubChunk1Size'] - 16);
            }
            
            if ($i == 0) {
                // create the final file's header, size will be adjusted later
                $out_data = $header . 'data';
            }
            
            $samples     = unpack('VSamples', substr($data, $dataPos + 4, 4));
            $out_data   .= substr($data, $dataPos + 8, $samples['Samples']);
            $numSamples += $samples['Samples'];
        }

        $filesize  = strlen($out_data);
        $chunkSize = $filesize - 8;
        $dataCSize = $numSamples;
        
        $out_data = substr_replace($out_data, pack('V', $filesize), 4, 4);
        $out_data = substr_replace($out_data, pack('V', $numSamples), 40 + ($info['SubChunk1Size'] - 16), 4);

        return $out_data;
    }
    
    protected function scrambleAudioData(&$data, $format)
    {
        if ($format == 'wav') {
            $start = strpos($data, 'data') + 4; // look for "data" indicator
            if ($start === false) $start = 44;  // if not found assume 44 byte header
        } else { // mp3
            $start = 4; // 4 byte (32 bit) frame header
        }
         
        $start  += rand(1, 64); // randomize starting offset
        $datalen = strlen($data) - $start - 256; // leave last 256 bytes unchanged
         
        for ($i = $start; $i < $datalen; $i += 64) {
            $ch = ord($data{$i});
            if ($ch < 9 || $ch > 119) continue;

            $data{$i} = chr($ch + rand(-8, 8)); // slightly change value of the byte to create blips or distortions of sound
        }
    }
    
    function frand()
    {
        return 0.0001 * rand(0,9999);
    }
    
    protected function initColor($color, $default)
    {
        if ($color == null) {
            return new Securimage_Color($default);
        } else if (is_string($color)) {
            try {
                return new Securimage_Color($color);
            } catch(Exception $e) {
                return new Securimage_Color($default);
            }
        } else if (is_array($color) && sizeof($array) == 3) {
            return new Securimage_Color($color[0], $color[1], $color[2]);
        } else {
            return new Securimage_Color($default);
        }
    }
}


/**
 * Color object for Securimage CAPTCHA
 *
 * @version 3.0
 * @since 2.0
 * @package Securimage
 * @subpackage classes
 *
 */
class Securimage_Color
{
    public $r;
    public $g;
    public $b;

    /**
     * Create a new Securimage_Color object.<br />
     * Constructor expects 1 or 3 arguments.<br />
     * When passing a single argument, specify the color using HTML hex format,<br />
     * when passing 3 arguments, specify each RGB component (from 0-255) individually.<br />
     * $color = new Securimage_Color('#0080FF') or <br />
     * $color = new Securimage_Color(0, 128, 255)
     * 
     * @param string $color
     * @throws Exception
     */
    public function __construct($color = '#ffffff')
    {
        $args = func_get_args();
        
        if (sizeof($args) == 0) {
            $this->r = 255;
            $this->g = 255;
            $this->b = 255;
        } else if (sizeof($args) == 1) {
            // set based on html code
            if (substr($color, 0, 1) == '#') {
                $color = substr($color, 1);
            }
            
            if (strlen($color) != 3 && strlen($color) != 6) {
                throw new InvalidArgumentException(
                  'Invalid HTML color code passed to Securimage_Color'
                );
            }
            
            $this->constructHTML($color);
        } else if (sizeof($args) == 3) {
            $this->constructRGB($args[0], $args[1], $args[2]);
        } else {
            throw new InvalidArgumentException(
              'Securimage_Color constructor expects 0, 1 or 3 arguments; ' . sizeof($args) . ' given'
            );
        }
    }
    
    protected function constructRGB($red, $green, $blue)
    {
        if ($red < 0)     $red   = 0;
        if ($red > 255)   $red   = 255;
        if ($green < 0)   $green = 0;
        if ($green > 255) $green = 255;
        if ($blue < 0)    $blue  = 0;
        if ($blue > 255)  $blue  = 255;
        
        $this->r = $red;
        $this->g = $green;
        $this->b = $blue;
    }
    
    protected function constructHTML($color)
    {
        if (strlen($color) == 3) {
            $red   = str_repeat(substr($color, 0, 1), 2);
            $green = str_repeat(substr($color, 1, 1), 2);
            $blue  = str_repeat(substr($color, 2, 1), 2);
        } else {
            $red   = substr($color, 0, 2);
            $green = substr($color, 2, 2);
            $blue  = substr($color, 4, 2); 
        }
        
        $this->r = hexdec($red);
        $this->g = hexdec($green);
        $this->b = hexdec($blue);
    }
}
