<?php
// GoogleAuthenticator.php
// Gecorrigeerde native PHP class voor Google/Microsoft Authenticator

class GoogleAuthenticator {
    protected $_codeLength = 6;

    public function createSecret($secretLength = 16) {
        $validChars = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H',
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P',
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X',
            'Y', 'Z', '2', '3', '4', '5', '6', '7'
        );
        $secret = '';
        for ($i = 0; $i < $secretLength; $i++) {
            $secret .= $validChars[rand(0, 31)];
        }
        return $secret;
    }

    public function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }
        $secretKey = $this->_base32Decode($secret);
        $time = chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);
        for ($i = 7; $i >= 0; $i--) {
            $time[$i] = chr($timeSlice & 0xFF);
            $timeSlice >>= 8;
        }
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        $offset = ord($hmac[19]) & 0x0F;
        $hashpart = substr($hmac, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, $this->_codeLength);
        return str_pad($value % $modulo, $this->_codeLength, '0', STR_PAD_LEFT);
    }

    public function getQRText($user, $secret, $title = 'MST Logistics') {
        return 'otpauth://totp/'.rawurlencode($title).':'.rawurlencode($user).'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($title);
    }

    public function verifyCode($secret, $code, $discrepancy = 2, $currentTimeSlice = null) {
        if ($currentTimeSlice === null) {
            $currentTimeSlice = floor(time() / 30);
        }
        if (strlen($code) != 6) { return false; }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = $this->getCode($secret, $currentTimeSlice + $i);
            if ($calculatedCode == $code) {
                return true;
            }
        }
        return false;
    }

    protected function _base32Decode($secret) {
        if (empty($secret)) return '';
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $secret = strtoupper($secret);
        $secret = str_replace('=', '', $secret);
        $secretSplit = str_split($secret);
        
        $binaryString = "";
        foreach ($secretSplit as $char) {
            if (!isset($base32charsFlipped[$char])) return false;
            $binaryString .= str_pad(base_convert($base32charsFlipped[$char], 10, 2), 5, '0', STR_PAD_LEFT);
        }
        
        $bytes = str_split($binaryString, 8);
        $out = "";
        foreach ($bytes as $byte) {
            if (strlen($byte) == 8) {
                $out .= chr(base_convert($byte, 2, 10));
            }
        }
        return $out;
    }
}