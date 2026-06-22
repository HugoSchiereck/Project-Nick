<?php
// GoogleAuthenticator.php
// Native PHP class voor Google/Microsoft Authenticator (dependency-free)

class GoogleAuthenticator {
    protected $_codeLength = 6;

    public function createSecret($secretLength = 16) {
        $validChars = array(
            'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
            'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
            'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
            'Y', 'Z', '2', '3', '4', '5', '6', '7'  // 31
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

    public function verifyCode($secret, $code, $discrepancy = 1, $currentTimeSlice = null) {
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
        $paddingCharCount = substr_count($secret, '=');
        $allowedPaddingCounts = array(6, 4, 3, 1, 0);
        if (!in_array($paddingCharCount, $allowedPaddingCounts)) return false;
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedPaddingCounts[$i] && substr($secret, -($allowedPaddingCounts[$i])) != str_repeat('=', $allowedPaddingCounts[$i])) return false;
        }
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = "";
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = "";
            if (!in_array($secret[$i], str_split($base32chars))) return false;
            for ($j = 0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@$base32charsFlipped[$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }
            $threeBits = str_split($x, 8);
            for ($z = 0; $j < count($threeBits); $z++) {
                $binaryString .= (($y = chr(base_convert($threeBits[$z], 2, 10))) || ord($y) == 0) ? $y : "";
            }
        }
        return $binaryString;
    }
}