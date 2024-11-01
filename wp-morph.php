<?php // -*- mode: php -*- vim: expandtab ts=8 sw=8
/*
Plugin Name: WP-Morph
Plugin URI: http://neuromancer.inf.um.es/blog/index.php?s=wp-morph&submit=Search
Description: Fool spammers by creating a complicated javascript program to be executed by a real browser.
Author: Diego Sevilla Ruiz
Version: 1.6
Author URI: http://neuromancer.inf.um.es/blog
Id: $Id: wp-morph.php 11616 2007-04-23 23:36:37Z dsevilla $
*/

////// Config values:
// * form_valid_minutes is the number of minutes that the form is valid
//                      since the form appears in the screen till the user
//                      pushes the "submit" button. (15 minutes by default).
$form_valid_minutes = 15;
////// End of config values.


// Check the result through the MD5 sum.
function morph_check_md5($comment) {
        global $form_valid_minutes;

        // Experimental: check only comments proper.
        // Let pingbacks and trackbacks
        if (!(get_comment_type() === 'comment'))
                return $comment;
        
        // Get rnd_val from WP option database
        $rnd_val = get_option('wp_morph_seed');

        // Check the fast check :)
        if ('spammers_go_home' == trim(strip_tags($_POST['checkpoint'])) )
        {
                // Check that md5 of check is the same than produced
                $v = $_POST['calc_value'];

                // This value cannot be known by spammers
                $v += $rnd_val;

                // Then, try at most "$form_valid_minutes" minutes back.
                $d = (integer)(time() / 60);
                $t = $d - $form_valid_minutes;

                while ($t <= $d)
                {
                        $v2 = $v + $t;
                        $v2 = md5($v2);

                        if ($v2 == $_POST['result_md5'])
                                return $comment;
                        $t++;
                }
        }

        die( "This weblog is protected by WP-Morph. This is to prevent
             comment spam. You have to have JavaScript enhabled in order
             to post comments here. Sorry for the inconvenience." );
}

global $wp_version;
if ($wp_version{0} == '2')
        add_filter('pre_comment_approved', 'morph_check_md5');
else
        add_filter('post_comment_text', 'morph_check_md5');

// Output form actions
function morph_output_form_items($page) {

        // Check if we have to change the $rnd_val
        $rnd_val = get_option('wp_morph_seed');
        $rnd_val_last_updated = get_option('wp_morph_seed_last_updated');

        // Change seed every day
        if ( !$rnd_val || ((time () - $rnd_val_last_updated) > 86400) )
        {
                $rnd_val = rand (1001, 60001);
                update_option('wp_morph_seed', $rnd_val);
                update_option('wp_morph_seed_last_updated', time());
        }

        // We have three arrays of random size. Complicated calculus can
        // be made here.
        // 6 to 20 variables
        $nvars = rand(6,20);
        $maxval = rand(1000,10000);
        $vvv = array();

        for ($i = 0 ; $i < $nvars; $i++)
        {
                $v = rand(1,$maxval - 1);
                $js_str .= 'v' . $i . '=' . $v . ';';
                $vvv[$i] = $v;
        }

        $nops = rand(3,20);
        for ($i = 0; $i < $nops; $i++)
        {
                // Operator
                $op = rand(0,5);

                // Select two variables and result, random
                $v1 = rand(0, $nvars - 1);
                $v2 = rand(0, $nvars - 1);
                $v3 = rand(0, $nvars - 1);

                switch($op)
                {
                        // +
                case '0':
                        $vvv[$v3] = ($vvv[$v1] + $vvv[$v2]) % $maxval;
                        $js_str .= 'v'.$v3.'=(v'.$v1
                            . '+v'.$v2.')%'. $maxval .';';
                        break;
                        // -
                case '1':
                        $vvv[$v3] = ($vvv[$v1] - $vvv[$v2]) % $maxval;
                        $js_str .= 'v'.$v3.'=(v'.$v1
                            . '-v'.$v2.')%'. $maxval .';';
                        break;
                        // *
                case '2':
                        $vvv[$v3] = ($vvv[$v1] * $vvv[$v2]) % $maxval;
                        $js_str .= 'v'.$v3.'=(v'.$v1
                            . '*v'.$v2.')%'. $maxval .';';
                        break;

                        // if, >
                case '3':
                        $v4 = rand (1, $maxval - 1);

                        $js_str .= 'if ( v' . $v1 . ' > '. $v4 . ')
                                    { v' . $v2 . ' = v' . $v3 . '; }';

                        if ($vvv[$v1] > $v4)
                        {
                                $vvv[$v2] = $vvv[$v3];
                        }
                        break;

                        // if, <
                case '4':
                        $v4 = rand (1, $maxval - 1);

                        $js_str .= 'if ( v' . $v1 . ' < '. $v4 . ')
                                    { v' . $v2 . ' = v' . $v3 . '; }';

                        if ($vvv[$v1] < $v4)
                        {
                                $vvv[$v2] = $vvv[$v3];
                        }
                        break;

                        // while
                case '5':
                        $v4 = rand (1, 100);

                        // Quick and dirty check
                        if ($v1 == $v2)
                                break;

                        $js_str .= 'v'. $v1 .'=Math.abs(v'.$v1.');
                                   v'. $v1 .'%='. $v4 .'; while (v'.$v1.'--) {
                                   v'. $v2.'++; }';

                        // Calc the final value
                        $vvv[$v1] = abs ($vvv[$v1]);
                        $vvv[$v2] += $vvv[$v1] % $v4;
                        $vvv[$v1] = -1;
                        break;
                }

        }

        $final_val = 0;

        $js_str .= "eElement.value = (";
        for ($i = 0 ; $i < $nvars; $i++)
        {
                if ($i != 0)
                {
                        $js_str .= '+';
                }
                $js_str .= 'v' . $i . '%' . $maxval;
                $final_val += ($vvv[$i] % $maxval);
        }


        $js_str .= ')%'. $maxval.';';

        $final_val %= $maxval;

        // Add the secret quantity
        $final_val += $rnd_val;

        // Add the epoh down to minutes
        $final_val += (integer)(time() / 60);

        // Calc the md5 of the value
        $md5_value = md5($final_val);

        // Write in hidden field
        $page = str_replace('<input type="hidden" name="comment_post_ID"',
           '<input type="hidden" name="checkpoint" value="spammers_go_home" />
            <input type="hidden" name="result_md5" value="'
                            . $md5_value . '" />
            <input type="hidden" id="chk" name="calc_value" value="" />
            <input type="hidden" name="comment_post_ID"', $page);

        // The form action
        $page = str_replace('<form',
                            '<form onsubmit="go_anti_spam();" ',
                            $page);

        // The jscript
        $page = str_replace('</head>', '<script type="text/javascript">
//<![CDATA[

    function go_anti_spam()
    {
        eElement = document.getElementById("chk");
        if(!eElement){ return false; }
        else
        {
            '.$js_str.'
            return true;
        }
    }//]]></script></head>', $page);

        return $page;
}

function morph_call_output_items() {
        ob_start('morph_output_form_items');
}

function morph_flush() {
        ob_end_flush();
}

// Now we set that function up to execute when the wp_head action is called
add_action('wp_head', 'morph_call_output_items');

// This one needed to flush the buffer started in the output modification
add_action('shutdown', "morph_flush");

?>
