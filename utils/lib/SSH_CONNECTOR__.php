<?php
/**
 * ISC License
 *
 * Copyright (c) 2019, Palo Alto Networks Inc.
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

set_include_path(dirname(__FILE__) . '/../' . PATH_SEPARATOR . get_include_path());
require_once dirname(__FILE__) . "/../../lib/pan_php_framework.php";
require_once dirname(__FILE__) . "/../../utils/lib/UTIL.php";


class SSH_CONNECTOR__ extends UTIL
{
    public function utilStart()
    {
        $this->usageMsg = PH::boldText('USAGE: ') ."\n".
            "  - download via SSH config from different Firewall vendors:\n".
            "  - pan-os-php type=ssh-connector in=[USER@MGMT-IP] out=TEXTFILEconfig.txt vendor=[paloalto/ciscoasa/netscreen/srx/sonicwall/fortinet]\n".
            "\n".
            "  - send CLI commands to Palo Alto Networks PAN-OS, possible to store output in txt file\n".
            "  - multiple commands must be separate with '/' \n".
            "  - pan-os-php type=ssh-connector in=[USER@MGMT-IP] out=TEXTFILE.txt command=[show system info/show session info]\n".
            "\n".
            "  - load Palo Alto Networks PAN-OS set commands from file\n".
            "  - pan-os-php type=ssh-connector in=[USER@MGMT-IP] setcommand-file=FILE.txt setcommand-maxlinecount=20\n".
            "\n".
            "  possible additional arguments:\n".
            "  password=PW\n".
            "  timeout=10\n".
            "  nohiddenpw\n".
            "  debug\n";


        PH::processCliArgs();

        $this->main();
    }

    public function main( )
    {
        //Todo: SWASCHKUT input of more then one 3rd party IP address

        //arguments:
        //vendor
        //version

        /*

        ^(?:
            (?:
                (?:
                    (?: 25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}
                            (?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?:(?=.{4,253})(((?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63})))(,\s*(?:(?:(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))|(?=.{4,253})(((?!-)[a-zA-Z0-9-]{1,63}(?<!-)\.)+[a-zA-Z]{2,63})))*$

         */

        define('NET_SSH2_LOGGING', NET_SSH2_LOG_COMPLEX);


        #PH::processCliArgs();

        $ip = "";
        $user = "";
        $password = "";
        $vendor = "";

        $debug = FALSE;
        $output_string = "";
        $hiddenPW = TRUE;
        $RSAkey = null;
        $inputpassword = null;
        $timeout = 10;

        $setcommandMaxLine = 20;

//output string manipulation
        $manipulate = FALSE;
        $includesearch = FALSE;
        $search_start = "";
        $search_end = "";


        if( isset(PH::$args['nohiddenpw']) )
            $hiddenPW = FALSE;

        if( isset(PH::$args['debug']) )
            $debug = TRUE;

        if( isset(PH::$args['timeout']) )
            $timeout = PH::$args['timeout'];


        if( isset(PH::$args['in']) )
        {
            $ip = PH::$args['in'];
            if( strpos($ip, "@") != FALSE )
            {
                $tmp_ip = explode("@", $ip);
                $ip = $tmp_ip[1];
                $user = $tmp_ip[0];
            }
        }
        else
            $this->display_error_usage_exit('"in=[USER@MGMT-IP]" is missing from arguments');


        if( isset(PH::$args['key']) )
            $RSAkey = PH::$args['key'];

        if( isset(PH::$args['password']) )
            $inputpassword = PH::$args['password'];

        if( isset(PH::$args['vendor']) )
        {
            $vendor = PH::$args['vendor'];
            $vendor = strtolower($vendor);
        }
        elseif( isset(PH::$args['setcommand-file']) )
        {
            $commandfile = PH::$args['setcommand-file'];
            $commands = file($commandfile, FILE_IGNORE_NEW_LINES);
            array_unshift($commands, 'configure');

            if( isset(PH::$args['setcommand-maxlinecount']) )
                $setcommandMaxLine = PH::$args['setcommand-maxlinecount'];

            if( isset(PH::$args['out']) )
            {
                $outfile = PH::$args['out'];
            }
            else
                $this->display_error_usage_exit('"out=[outputfile.txt]" is missing from arguments');
        }
        elseif( isset(PH::$args['command']) )
        {
            $commands = PH::$args['command'];
            $commands = explode("/", $commands);
            array_unshift($commands , 'set cli pager off');

            if( isset(PH::$args['out']) )
            {
                $outfile = PH::$args['out'];
            }
            else
                $this->display_error_usage_exit('"out=[outputfile.txt]" is missing from arguments');
        }
        else
            $this->display_error_usage_exit("'vendor=[VENDOR]' OR 'command=[COMMANDS]' OR 'setcommand-file=[FILE]' is missing from arguments");


        if( $vendor == "paloalto" )
        {
            //VALIDATION done
            $commands[] = "set cli pager off";
            $commands[] = "set cli op-command-xml-output on";
            $commands[] = "show config candidate";
        }
        elseif( $vendor == "ciscoasa" )
        {
            //VALIDATION done against an instance in AWS (ASAv)
            #$commands[] = "pager 0";
            $commands[] = "enable";
            $commands[] = ""; //needed if password request, but password is BLANK
            $commands[] = "terminal pager 0";
            $commands[] = "more system:running-config";
            #$commands[] = "show running";
        }
        elseif( $vendor == "netscreen" )
        {
            $commands[] = "set console page 0";
            $commands[] = "get config";
        }
        elseif( $vendor == "srx" )
        {
            //VALIDATION done against an instance in AWS
            $commands[] = "show configuration | display xml | no-more";
        }
        elseif( $vendor == "sonicwall" )
        {
            $commands[] = "no cli pager session";
            $commands[] = "show current-config";
        }
        elseif( $vendor == "fortinet" )
        {
            $commands[] = "config system console";
            $commands[] = "set output standard";
            $commands[] = "end";
            $commands[] = "show full-configuration";
        }
        elseif( $vendor != "" )
        {
            derr("VENDOR: " . $vendor . " not yet supported\n");
        }

/////////////////////////////////////////////////////////////////////////////
//USER / PASSWORD | KEY input
/////////////////////////////////////////////////////////////////////////////
///
///

        $handle = fopen("php://stdin", "r");
        if( $user == "" )
        {
            PH::print_stdout("** Please enter username below and hit enter:  ");

            #$handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            $user = trim($line);
        }


        if( $inputpassword != null )
        {
            $password = $inputpassword;
        }
        elseif( $RSAkey == null )
        {
            $password = PanAPIConnector::hiddenPWvalidation($user, $hiddenPW, $handle);
        }
        else
        {
            $pw_prompt = " --- using USER: '" . $user . "' , and private key.";
            PH::print_stdout($pw_prompt);

            $password = new Crypt_RSA();
            $private_key = file_get_contents($RSAkey);
            $password->loadKey($private_key);
        }


############################################
//START SSH connection
############################################

        $ssh = new RUNSSH($ip, $user, $password, $commands, $output_string, $timeout, 22, $setcommandMaxLine);

############################################
//START output string manipulation
############################################

#$output_string = str_ireplace("\x0D", "", $output_string);
        $output_string = str_ireplace("\r", "", $output_string);


        if( $vendor == "paloalto" )
        {
            $manipulate = TRUE;
            $includesearch = TRUE;

            $search_start = "<config ";
            $search_end = "</config>";
        }
        elseif( $vendor == "srx" )
        {
            $manipulate = TRUE;
            $includesearch = TRUE;

            $search_start = "<configuration ";
            $search_end = "</configuration>";
        }
        elseif( $vendor == "ciscoasa" )
        {
            $manipulate = TRUE;
            $includesearch = TRUE;

            $search_start = ": Saved";
            $search_end = ": end";
        }
        elseif( $vendor == "fortinet" )
        {
            $manipulate = FALSE;
            $includesearch = TRUE;
        }

        if( $manipulate )
        {
            $start = strpos($output_string, $search_start);
            $end = strrpos($output_string, $search_end);

            if( $includesearch )
            {
                $length = ($end + strlen($search_end) - $start);
                $output_string = substr($output_string, $start, $length);
            }
            else
            {
                $length = ($end - $start - strlen($search_start) - 1);
                $output_string = substr($output_string, $start + strlen($search_start) + 1, $length);
            }
        }

        if( isset(PH::$args['command']) || isset(PH::$args['setcommand-file']) )
        {
            PH::print_stdout("write output into file: " . $outfile);
            file_put_contents($outfile, $output_string, FILE_APPEND);
        }
        else
        {
            PH::print_stdout("write output into file: " . $vendor . "-config.txt");
            file_put_contents($vendor . "-config.txt", $output_string);
        }


########################################################################################
########################################################################################

//Todo: how to export 3rd party vendor config

        /*


         * SIDEWINDER (FORCEPOINT)
                cf interface q > config_sidewinder.txt
                cf service q >> config_sidewinder.txt
                cf servicegroup q >> config_sidewinder.txt
                cf policy q >> config_sidewinder.txt
                cf route q >> config_sidewinder.txt
                cf ipaddr q >> config_sidewinder.txt
                cf iprange q >> config_sidewinder.txt
                cf subnet q >> config_sidewinder.txt
                cf netmap q >> config_sidewinder.txt
                cf domain q >> config_sidewinder.txt
                cf static q >> config_sidewinder.txt
                cf netgroup q >> config_sidewinder.txt
                cf application q >> config_sidewinder.txt
                cf appgroup q >> config_sidewinder.txt
                cf host q >> config_sidewinder.txt
         *
         *
         * STONESOFT
         *
                Migration of Stonesoft configurations require a Two-Step process.
                Please, read the following instructions to support the process.
                1) First Step: BROWSE for Stonesoft XML configuration files using the Single File or Multiple Files options.
                    Policy names and Domain Names will be presented
                2) Second Step.
                    Select the policies wishing to migrate and click on IMPORT SELECTED POLICIES


         * SOPHOS
                 //Todo: NEEDED config files:
                Sophos UTM API export (copy / past)
                https://www.sophos.com/en-us/medialibrary/PDFs/documentation/UTMonAWS/Sophos-UTM-RESTful-API.pdf?la=en
                https://ip_address_of_ UTM:4444/api/


                //copy all these information into one file
                network/host
                network/dns_host
                network/dns_group
                network/group
                network/range
                network/network
                network/interface_network

                service/group
                service/tcp
                service/udp
                service/tcpudp

                packetfilter/packetfilter




                //rule order information MUST be placed in a seperate file
                rule order
                nodes/packetfilter.rules


        #########################################################
        DONE
        DONE

        * CISCOASA: SSH        DONE
                terminal pager 0
                more system:running-config / show running


         * NETSCREEN: SSH         DONE
                "get config"


         * SRX: SSH        DONE
                "show configuration | display xml | no-more"


         * SONICWALL        DONE
                show current-config
         *

         * FORTINET:        DONE

        //Cisco: multi context

        changeto system
        show tech
        changeto context [hostname]

         */

    }
}