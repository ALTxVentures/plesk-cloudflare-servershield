<?php

/*
 * ip_in_range.php - Function to determine if an IP is located in a
 *                   specific range as specified via several alternative
 *                   formats.
 *
 * Network ranges can be specified as:
 * 1. Wildcard format:     1.2.3.*
 * 2. CIDR format:         1.2.3/24  OR  1.2.3.4/255.255.255.0
 * 3. Start-End IP format: 1.2.3.0-1.2.3.255
 *
 * Return value BOOLEAN : ip_in_range($ip, $range);
 *
 * Copyright 2008: Paul Gregg <pgregg@pgregg.com>
 * 10 January 2008
 * Version: 1.2
 *
 * Source website: http://www.pgregg.com/projects/php/ip_in_range/
 * Version 1.2
 *
 * This software is Donationware - if you feel you have benefited from
 * the use of this tool then please consider a donation. The value of
 * which is entirely left up to your discretion.
 * http://www.pgregg.com/donate/
 *
 * Please do not remove this header, or source attibution from this file.
 */

/*
* Modified by James Greene <james@cloudflare.com> to include IPV6 support
* (original version only supported IPV4).
* 21 May 2012
*/

class Modules_servershield_CFIPRUtil {

    static $CloudFlareIPv4 = array("199.27.128.0/21",
                                  "173.245.48.0/20",
                                  "103.21.244.0/22",
                                  "103.22.200.0/22",
                                  "103.31.4.0/22",
                                  "141.101.64.0/18",
                                  "108.162.192.0/18",
                                  "190.93.240.0/20",
                                  "188.114.96.0/20",
                                  "197.234.240.0/22",
                                  "198.41.128.0/17",
                                  "162.158.0.0/15",
                                  "104.16.0.0/12");

    static $CloudFlareIPv6 = array("2400:cb00::/32",
                                "2606:4700::/32",
                                "2803:f800::/32",
                                "2405:b500::/32",
                                "2405:8100::/32");



    public function isCFIP($ip) {

        if($this->isIPv4($ip)) {
            foreach(static::$CloudFlareIPv4 as $ipr) {
                if($this->is_ipv4_within($ip, $ipr)){
                    return true;
                }
            }
        } else if ($this->isIPv6($ip)) {
            foreach(static::$CloudFlareIPv6 as $ipr) {
                if($this->is_ipv6_within($ip, $ipr)){
                    return true;
                }
            }
        } else {
            return false;
        }

        return false;
    }

    private function isIPv6( $ipv6 ) { return filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);  }

    private function isIPv4( $ipv4 ) { return filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);  }

    private function is_ipv4_within($inet,& $ranges) {
        if ($this->isIPv4($inet) && $ranges ) {
            if (! is_array($ranges) ) $ranges = array($ranges);
            foreach ($ranges as $range) {
                if ( $this->cidrIPv4Match($inet,$range))  return TRUE;
            }
        }
    }

    private function is_ipv6_within($inet,& $ranges) {
        if ($this->isIPv6($inet) && $ranges ) {
            if (! is_array($ranges) ) $ranges = array($ranges);
            foreach ($ranges as $range) {
                if ( $this->cidrIPv6Match($inet,$range))  return TRUE;
            }
        }
    }

    private function cidrIPv4Match($ip,$cidr){
        list($net,$maskbits) = explode("/",$cidr);
        return $this->cidrIPv4RawMatch($ip,$net,$maskbits);
    }

    private function cidrIPv4RawMatch($ip,$net,$maskbits) {
        if ( ! $maskbits ) return FALSE;

        $binaryip   =$this->inet_to_bits($ip, 4);
        $binarynet  =$this->inet_to_bits($net, 4);

        $ip_net_bits=substr($binaryip,0,$maskbits);
        $net_bits   =substr($binarynet,0,$maskbits);

        return ($ip_net_bits!==$net_bits) ? FALSE : TRUE;
    }

    private function cidrIPv6Match($ip,$cidr){
        list($net,$maskbits) = explode("/",$cidr);
        return $this->cidrIPv6RawMatch($ip,$net,$maskbits);
    }

    private function cidrIPv6RawMatch($ip,$net,$maskbits) {
        if ( ! $maskbits ) return FALSE;

        $binaryip   =$this->inet_to_bits($ip, 6);
        $binarynet  =$this->inet_to_bits($net, 6);

        $ip_net_bits=substr($binaryip,0,$maskbits);
        $net_bits   =substr($binarynet,0,$maskbits);

        return (strpos($ip_net_bits, $net_bits)  !== false);
    }

    private function inet_to_bits($inet,$ip_version = 4) {
       $unpacked    = unpack( $ip_version == 6 ? "A16" : "A4", inet_pton($inet));
       $unpacked    = str_split($unpacked[1]);
       $binaryip    = "";


       foreach ($unpacked as $char) {
            $binaryip .= str_pad(decbin(ord($char)), 8, "0", STR_PAD_LEFT);
       }

       return $binaryip;
    }

}