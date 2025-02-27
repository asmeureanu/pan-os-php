<?php
/**
 * ISC License
 *
 * Copyright (c) 2014-2018, Palo Alto Networks Inc.
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



DHCPCallContext::$supportedActions['display'] = Array(
    'name' => 'display',
    'MainFunction' => function ( DHCPCallContext $context ) {
        $object = $context->object;
        PH::print_stdout("     * " . get_class($object) . " '{$object->name()}'");
        PH::$JSON_TMP['sub']['object'][$object->name()]['name'] = $object->name();
        PH::$JSON_TMP['sub']['object'][$object->name()]['type'] = get_class($object);

        if( count($object->server_leases) > 0 )
        {
            PH::print_stdout("       SERVER reservation:");

            foreach( $object->server_leases as $lease )
            {
                #PH::print_stdout("       - "."IP: ".$lease['ip']." | mac: ".$lease['mac']);
                PH::print_stdout("       - " . "" . $lease['ip'] . " | " . $lease['mac']);
                PH::$JSON_TMP['sub']['object'][$object->name()]['server']['reserved'][] = $lease;
            }
        }
        if( count($object->server_ip_pool) > 0 )
        {
            PH::print_stdout("       SERVER ip-pool:");

            foreach( $object->server_ip_pool as $ip_pool )
            {
                PH::print_stdout("       - " . "" . $ip_pool);
                PH::$JSON_TMP['sub']['object'][$object->name()]['server']['ip-pool'][] = $ip_pool;
            }
        }

        if( count($object->relay_ipv4) > 0 )
        {
            if( $object->relay_ipv4_status )
                PH::print_stdout("       RELAY IPV4 [enabled]:" );
            else
                PH::print_stdout("       RELAY IPV4 [disabled]:" );
            foreach( $object->relay_ipv4 as $server )
            {
                PH::print_stdout("       - "."".$server);
                PH::$JSON_TMP['sub']['object'][$object->name()]['relay']['ipv4'][] = $server;
            }
        }

        if( count($object->relay_ipv6) > 0 )
        {
            if( $object->relay_ipv6_status )
                PH::print_stdout("       RELAY IPV6 [enabled]:" );
            else
                PH::print_stdout("       RELAY IPV6 [disabled]:" );
            foreach( $object->relay_ipv6 as $server )
            {
                PH::print_stdout("       - "."".$server);
                PH::$JSON_TMP['sub']['object'][$object->name()]['relay']['ipv6'][] = $server;
            }
        }

    },

);

DHCPCallContext::$supportedActions['dhcp-server-reservation-create'] = Array(
    'name' => 'dhcp-server-reservation',
    'MainFunction' => function ( DHCPCallContext $context )
    {
        $object = $context->object;

        $xpath = $object->getXPath()."/server/reserved";

        $tmp_ip = $context->arguments['ip'];
        $tmp_mac = $context->arguments['mac'];
        $tmp_mac = str_replace("$$", ":", $tmp_mac);

        $tmp_array = array();
        $tmp_array[] = array("ip"=> $tmp_ip, "mac"=> $tmp_mac);

        $element = "";
        foreach( $tmp_array as $entry)
        {
            $ip = $entry['ip'];
            $mac = $entry['mac'];


            if( $context->isAPI )
            {
                $element .= "<entry name='".$ip."'><mac>".$mac."</mac></entry>";
            }
            else
            {
                $tmp_server_xml = DH::findFirstElementOrCreate( 'server', $object->xmlroot );
                $tmp_reserved_xml = DH::findFirstElementOrCreate( 'reserved', $tmp_server_xml );
                $tmp_entry_xml = DH::findFirstElementByNameAttrOrCreate( "entry", $ip, $tmp_reserved_xml, $object->xmlroot->ownerDocument);
                $tmp_mac_xml = DH::createElement( $tmp_entry_xml, "mac" );
                $tmp_mac_xml->textContent = $mac;
            }
        }

        if( $context->isAPI )
        {
            $con = findConnectorOrDie($object);
            $con->sendSetRequest($xpath, $element);
        }
    },
    'args' => array(
        'ip' => array('type' => 'string', 'default' => 'false'),
        'mac' => array('type' => 'string', 'default' => 'false'),
    ),
);

DHCPCallContext::$supportedActions['exportToExcel'] = array(
    'name' => 'exportToExcel',
    'MainFunction' => function (DHCPCallContext $context) {
        $object = $context->object;
        $context->objectList[] = $object;
    },
    'GlobalInitFunction' => function (DHCPCallContext $context) {
        $context->objectList = array();
    },
    'GlobalFinishFunction' => function (DHCPCallContext $context) {
        $args = &$context->arguments;
        $filename = $args['filename'];

        if( isset( $_SERVER['REQUEST_METHOD'] ) )
            $filename = "project/html/".$filename;

        $addWhereUsed = FALSE;
        $addUsedInLocation = FALSE;

        $optionalFields = &$context->arguments['additionalFields'];

        if( isset($optionalFields['WhereUsed']) )
            $addWhereUsed = TRUE;

        if( isset($optionalFields['UsedInLocation']) )
            $addUsedInLocation = TRUE;

        $headers = '<th>ID</th><th>template</th><th>location</th><th>name</th>';
        $headers .= '<th>SERVER reservation</th><th>SERVER ip-pool</th><th>RELAY IPv4</th><th>RELAY IPv6</th>';

        if( $addWhereUsed )
            $headers .= '<th>where used</th>';
        if( $addUsedInLocation )
            $headers .= '<th>location used</th>';


        $lines = '';

        $count = 0;
        if( isset($context->objectList) )
        {
            foreach( $context->objectList as $object )
            {
                $count++;

                /** @var DHCP $object */
                if( $count % 2 == 1 )
                    $lines .= "<tr>\n";
                else
                    $lines .= "<tr bgcolor=\"#DDDDDD\">";

                $lines .= $context->encloseFunction((string)$count);

                if( get_class($object->owner->owner) == "PANConf" )
                {
                    if( isset($object->owner->owner->owner) && $object->owner->owner->owner !== null && (get_class($object->owner->owner->owner) == "Template" || get_class($context->subSystem->owner) == "TemplateStack" ) )
                    {
                        $lines .= $context->encloseFunction($object->owner->owner->owner->name());
                        $lines .= $context->encloseFunction($object->owner->owner->name());
                    }
                    else
                    {
                        $lines .= $context->encloseFunction("---");
                        $lines .= $context->encloseFunction($object->owner->owner->name());
                    }
                }


                $lines .= $context->encloseFunction($object->name());

                $tmpArray = array();
                foreach( $object->server_leases as $lease )
                    $tmpArray[] = $lease['ip']." | ".$lease['mac'];
                $lines .= $context->encloseFunction($tmpArray);

                $tmpString = "";
                #foreach( $object->server_ip_pool as $server )
                #    $tmpString .= $server;
                $lines .= $context->encloseFunction($object->server_ip_pool);

                $tmpArray = array();
                if( count($object->relay_ipv4) > 0 )
                {
                    if( $object->relay_ipv4_status )
                        $tmpArray[] = "[enabled]";
                    else
                        $tmpArray[] = "[disabled]";
                    foreach( $object->relay_ipv4 as $server )
                        $tmpArray[] = $server;
                }
                $lines .= $context->encloseFunction($tmpArray);

                $tmpArray = array();
                if( count($object->relay_ipv6) > 0 )
                {
                    if( $object->relay_ipv6_status )
                        $tmpArray[] = "[enabled]";
                    else
                        $tmpArray[] = "[disabled]";
                    foreach( $object->relay_ipv6 as $server )
                        $tmpArray[] = $server;
                }
                $lines .= $context->encloseFunction($tmpArray);

                if( $addWhereUsed )
                {
                    $refTextArray = array();
                    foreach( $object->getReferences() as $ref )
                        $refTextArray[] = $ref->_PANC_shortName();

                    $lines .= $context->encloseFunction($refTextArray);
                }
                if( $addUsedInLocation )
                {
                    $refTextArray = array();
                    foreach( $object->getReferences() as $ref )
                    {
                        $location = PH::getLocationString($object->owner);
                        $refTextArray[$location] = $location;
                    }

                    $lines .= $context->encloseFunction($refTextArray);
                }


                $lines .= "</tr>\n";

            }
        }

        $content = file_get_contents(dirname(__FILE__) . '/html/export-template.html');
        $content = str_replace('%TableHeaders%', $headers, $content);

        $content = str_replace('%lines%', $lines, $content);

        $jscontent = file_get_contents(dirname(__FILE__) . '/html/jquery.min.js');
        $jscontent .= "\n";
        $jscontent .= file_get_contents(dirname(__FILE__) . '/html/jquery.stickytableheaders.min.js');
        $jscontent .= "\n\$('table').stickyTableHeaders();\n";

        $content = str_replace('%JSCONTENT%', $jscontent, $content);

        file_put_contents($filename, $content);
    },
    'args' => array('filename' => array('type' => 'string', 'default' => '*nodefault*'),
        'additionalFields' =>
            array('type' => 'pipeSeparatedList',
                'subtype' => 'string',
                'default' => '*NONE*',
                'choices' => array('WhereUsed', 'UsedInLocation', 'ResolveIP', 'NestedMembers'),
                'help' =>
                    "pipe(|) separated list of additional fields (ie: Arg1|Arg2|Arg3...) to include in the report. The following is available:\n" .
                    "  - NestedMembers: lists all members, even the ones that may be included in nested groups\n" .
                    "  - ResolveIP\n" .
                    "  - UsedInLocation : list locations (vsys,dg,shared) where object is used\n" .
                    "  - WhereUsed : list places where object is used (rules, groups ...)\n"
            )
    )

);