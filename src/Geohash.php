<?php
namespace Geohash;

class Geohash {

	private $bitss = array(16, 8, 4, 2, 1);
	private $neighbors = array();
	private $borders = array();
	
	private $coding = "0123456789bcdefghjkmnpqrstuvwxyz";
	private $codingMap = array();
	
	public function __construct()
	{
		$this->neighbors['right']['even'] = 'bc01fg45238967deuvhjyznpkmstqrwx';
		$this->neighbors['left']['even'] = '238967debc01fg45kmstqrwxuvhjyznp';
		$this->neighbors['top']['even'] = 'p0r21436x8zb9dcf5h7kjnmqesgutwvy';
		$this->neighbors['bottom']['even'] = '14365h7k9dcfesgujnmqp0r2twvyx8zb';
		
		$this->borders['right']['even'] = 'bcfguvyz';
		$this->borders['left']['even'] = '0145hjnp';
		$this->borders['top']['even'] = 'prxz';
		$this->borders['bottom']['even'] = '028b';
		
		$this->neighbors['bottom']['odd'] = $this->neighbors['left']['even'];
		$this->neighbors['top']['odd'] = $this->neighbors['right']['even'];
		$this->neighbors['left']['odd'] = $this->neighbors['bottom']['even'];
		$this->neighbors['right']['odd'] = $this->neighbors['top']['even'];
		
		$this->borders['bottom']['odd'] = $this->borders['left']['even'];
		$this->borders['top']['odd'] = $this->borders['right']['even'];
		$this->borders['left']['odd'] = $this->borders['bottom']['even'];
		$this->borders['right']['odd'] = $this->borders['top']['even'];
		
		for($i=0; $i<32; $i++) {
			$this->codingMap[substr($this->coding, $i, 1)] = str_pad(decbin($i), 5, "0", STR_PAD_LEFT);
		}
	}

	public function decode($hash)
	{
		$binary = "";
		$hl = strlen($hash);
		for ($i=0; $i<$hl; $i++) {
			$binary .= $this->codingMap[substr($hash, $i, 1)];
		}
		
		$bl = strlen($binary);
		$blat = "";
		$blng = "";
		for ($i=0; $i<$bl; $i++) {
			if ($i%2)
				$blat=$blat.substr($binary, $i, 1);
			else
				$blng=$blng.substr($binary, $i, 1);
		}
		
		$lat = $this->binDecode($blat, -90, 90);
		$lng = $this->binDecode($blng, -180, 180);
		
		$latErr = $this->calcError(strlen($blat), -90, 90);
		$lngErr = $this->calcError(strlen($blng), -180, 180);
				
		$latPlaces = max(1, -round(log10($latErr))) - 1;
		$lngPlaces = max(1, -round(log10($lngErr))) - 1;
		
		$lat = round($lat, $latPlaces);
		$lng = round($lng, $lngPlaces);

		return array($lat, $lng);
	}

	
	public function neighbors($srcHash)
	{
		$geohashPrefix = substr($srcHash, 0, strlen($srcHash) - 1);
	
		$neighbors['top'] = $this->calculateAdjacent($srcHash, 'top');
		$neighbors['bottom'] = $this->calculateAdjacent($srcHash, 'bottom');
		$neighbors['right'] = $this->calculateAdjacent($srcHash, 'right');
		$neighbors['left'] = $this->calculateAdjacent($srcHash, 'left');
		
		$neighbors['topleft'] = $this->calculateAdjacent($neighbors['left'], 'top');
		$neighbors['topright'] = $this->calculateAdjacent($neighbors['right'], 'top');
		$neighbors['bottomright'] = $this->calculateAdjacent($neighbors['right'], 'bottom');
		$neighbors['bottomleft'] = $this->calculateAdjacent($neighbors['left'], 'bottom');
	
		return $neighbors;
	}

	public function encode($lat, $lng, $len=12)
	{
		$plat = $this->precision($lat);
		$latbits = 1;
		$err = 45;
		while($err > $plat) {
			$latbits++;
			$err /= 2;
		}
		
		$plng = $this->precision($lng);
		$lngbits = 1;
		$err = 90;
		while($err > $plng) {
			$lngbits++;
			$err /= 2;
		}
		
		$bits = max($latbits, $lngbits);

		$lngbits = $bits;
		$latbits = $bits;
		$addlng = 1;
		while (($lngbits + $latbits) % 5 != 0) {
			$lngbits += $addlng;
			$latbits += !$addlng;
			$addlng = !$addlng;
		}
		
		$blat = $this->binEncode($lat, -90, 90, $latbits);
		$blng = $this->binEncode($lng, -180, 180, $lngbits);
		
		$binary = "";
		$uselng = 1;
		while (strlen($blat) + strlen($blng)) {
			if ($uselng) {
				$binary = $binary.substr($blng, 0, 1);
				$blng = substr($blng, 1);
			} else {
				$binary = $binary.substr($blat, 0, 1);
				$blat = substr($blat, 1);
			}
			$uselng = !$uselng;
		}
		
		$hash = "";
		for ($i=0; $i<strlen($binary); $i+=5) {
			$n = bindec(substr($binary, $i, 5));
			$hash = $hash.$this->coding[$n];
		}
		return substr($hash, 0, $len);
	}

	public function distance($lat1, $lon1, $lat2, $lon2)
	{
		$R = 6371393; //地球平均半径,单位米
		$dlat = deg2rad($lat2-$lat1);
		$dlon = deg2rad($lon2-$lon1);
		$a = pow(sin($dlat/2), 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * pow(sin($dlon/2), 2);
		$c = 2 * atan2(sqrt($a), sqrt(1-$a));
		$d = $R * $c;
		return round($d);
	}

	private function calculateAdjacent($srcHash, $dir)
	{
		$srcHash = strtolower($srcHash);
		$lastChr = $srcHash[strlen($srcHash) - 1];
		$type = (strlen($srcHash) % 2) ? 'odd' : 'even';
		$base = substr($srcHash, 0, strlen($srcHash) - 1);
		
		if (strpos($this->borders[$dir][$type], $lastChr) !== false) {
			$base = $this->calculateAdjacent($base, $dir);  
		}
			
		return $base . $this->coding[strpos($this->neighbors[$dir][$type], $lastChr)];
	}

	private function calcError($bits, $min, $max)
	{
		$err = ($max - $min) / 2;
		while ($bits--)
			$err /= 2;
		return $err;
	}

	private function precision($number)
	{
		$precision = 0;
		$pt = strpos($number,'.');
		if ($pt !== false) {
			$precision = -(strlen($number) - $pt - 1);
		}
		return pow(10, $precision) / 2;
	}
	
	private function binEncode($number, $min, $max, $bitcount)
	{
		if ($bitcount == 0)
			return "";
		$mid = ($min + $max) / 2;
		if ($number > $mid)
			return "1" . $this->binEncode($number, $mid, $max, $bitcount - 1);
		else
			return "0" . $this->binEncode($number, $min, $mid, $bitcount - 1);
	}
	
	private function binDecode($binary, $min, $max)
	{
		$mid = ($min + $max) / 2;
		if (strlen($binary) == 0)
			return $mid;
		$bit = substr($binary, 0, 1);
		$binary = substr($binary, 1);
		if ($bit == 1)
			return $this->binDecode($binary, $mid, $max);
		else
			return $this->binDecode($binary, $min, $mid);
	}
}