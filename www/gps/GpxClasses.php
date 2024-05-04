<?php

class GpxBounds implements JsonSerializable {
	private float $lonMin, $lonMax, $latMin, $latMax, $lonLast, $latLast;

	public function __construct() {
	}
	
	public static function initWithBounds(GpxBounds $gpxBounds) {
		$instance = new self();
		$instance->withBounds($gpxBounds);
		return $instance;
	}

	protected function withBounds(GpxBounds $gpxBounds) {
		$this->lonMin = $gpxBounds->getLonMin();
		$this->latMin = $gpxBounds->getLatMin();
		$this->lonMax = $gpxBounds->getLonMax();
		$this->latMax = $gpxBounds->getLatMax();
		$this->lonLast = $gpxBounds->getLonLast();
		$this->latLast = $gpxBounds->getLatLast();
	}
		
	public static function initWithValues($lon, $lat) {
		$instance = new self();
		$instance->withValues($lon, $lat);
		return $instance;
	}
	
	protected function withValues($lon, $lat) {
		$this->lonMin = $this->lonMax = $this->lonLast = floatval($lon);
		$this->latMin = $this->latMax = $this->latLast = floatval($lat);
	}

	public function addBounds(GpxBounds $gpxBounds) {
		if ($gpxBounds->getLonMin() < $this->lonMin) $this->lonMin = $gpxBounds->getLonMin();
		if ($gpxBounds->getLatMin() < $this->latMin) $this->latMin = $gpxBounds->getLatMin();
		if ($gpxBounds->getLonMax() > $this->lonMax) $this->lonMax = $gpxBounds->getLonMax();
		if ($gpxBounds->getLatMax() > $this->latMax) $this->latMax = $gpxBounds->getLatMax();
		$this->lonLast = $gpxBounds->getLonLast();
		$this->latLast = $gpxBounds->getLatLast();
	}

	public function addValues($lon, $lat) {
		if ($lon < $this->lonMin) $this->lonMin = floatval($lon);
		if ($lon > $this->lonMax) $this->lonMax = floatval($lon);
		if ($lat < $this->latMin) $this->latMin = floatval($lat);
		if ($lat > $this->latMax) $this->latMax = floatval($lat);
		$this->lonLast = floatval($lon);
		$this->latLast = floatval($lat);
	}

	public function toString() {
		return $this->latMin . ", " . $this->lonMin . " - " . $this->latMax . ", " . $this->lonMax;
	}
	public function getLonMin() { return $this->lonMin; }
	public function getLonMax() { return $this->lonMax; }
	public function getLonLast() { return $this->lonLast; }
	public function getLatMin() { return $this->latMin; }
	public function getLatMax() { return $this->latMax; }
	public function getLatLast() { return $this->latLast; }
	
	public function jsonSerialize() {
		$bounds=array();
		
		$bounds['min']['lat']=$this->latMin;
		$bounds['min']['lon']=$this->lonMin;
		$bounds['max']['lat']=$this->latMax;
		$bounds['max']['lon']=$this->lonMax;
		$bounds['last']['lat']=$this->latLast;
		$bounds['last']['lon']=$this->lonLast;
		$bounds['width']=GpsTools::distance(floatval($this->lonMin), floatval($this->latMin), floatval($this->lonMax), floatval($this->latMin));
		$bounds['height']=GpsTools::distance(floatval($this->lonMin), floatval($this->latMin), floatval($this->lonMin), floatval($this->latMax));
		
		return $bounds;
	}

}

class Gpx implements JsonSerializable {
	private $trks = array();
	private ?SimpleXMLElement $gpxNode;

	public function __construct(SimpleXMLElement $gpx) {
		$this->gpxNode = &$gpx;
		foreach ($gpx->trk as $trk)
			$this->trks[] = new GpxTrk($trk);
	}
	
	public function getTrks() { return $this->trks; }
	public function getWpts() { return $this->gpxNode->wpt; }
	public function getMeta() { return $this->gpxNode->metadata; }
	public function getVersion() {return $this->gpxNode['version']; }
	
	public function getGpxBounds() { 
		$gtxBounds;
		foreach ($this->trks as $trk) {
			if (! isset($gtxBounds))
				$gtxBounds = GpxBounds::initWithBounds($trk->getGpxBounds());
			else
				$gtxBounds->addBounds($trk->getGpxBounds());
		}

		return $gtxBounds;
	}
	
	public function getTime() {
		$time = "";
		
		if ($this->getMeta() != null)
			$time = $this->getMeta()->time;
		elseif (count($this->trks) > 0)
			$time = $this->trks[0]->getTime();
		
		return $time;
	}
	public function getLastTime() {
		$time = "";
		
		if  (count($this->trks) > 0)
			$time = $this->trks[count($this->trks) - 1]->getLastTime();
		
		return $time;
	}

	public function getName() {
		return ($this->getMeta() != null ? strval($this->getMeta()->name) : '');
	}

	public function jsonSerialize() {
		$gpx=array();
		$gpx['version']=strval($this->getVersion());
		$gpx['time']=convDate($this->getTime());
		$gpx['lastTime']=convDate($this->getLastTime());
		$gpx['name']=$this->getName();
		$gpx['distance']=0;
		$gpx['duration']=0;
		$gpx['bounds']=$this->getGpxBounds();

		$trks=array();
		foreach ($this->trks as $trk) {
			$trkNr=count($trks)+1;
			$trks[$trkNr]=array();
			$trks[$trkNr]['nr']=strval($trk->getNumber());
			$trks[$trkNr]['name']=strval($trk->getName());
			$trks[$trkNr]['time']=convDate($trk->getTime());
			$trks[$trkNr]['distance']=$trk->getLength();
			$trks[$trkNr]['duration']=formatDuration($trk->getDuration());
			$trks[$trkNr]['bounds']=$trk->getGpxBounds();
			$trks[$trkNr]['trkpts']=$trk->getTrkPts();
			$trks[$trkNr]['lastTime']=convDate($trk->getLastTime());
			
			$gpx['distance']+=$trk->getLength();
			$gpx['duration']+=$trk->getDuration();
		}

		$gpx['duration']=formatDuration($gpx['duration']);
		$gpx['trks']=$trks;
		
		return $gpx;
	}
}

class GpxTrk {
	private $segs = array();
	private ?SimpleXMLElement $trkNode;

	public function __construct(SimpleXMLElement $trk) {
		$this->trkNode = &$trk;
		foreach ($trk->trkseg as $seg) {
			$this->segs[] = new GpxTrkSeg($seg);
		}		
	}

	public function getName() { return $this->trkNode->name; }
	public function getNumber() { return $this->trkNode->number; }
	public function getDesc() { return $this->trkNode->desc; }
	public function getTrkSegs() { return $this->segs; }
	public function getTrkPts() { 
		$cnt = 0;
		foreach ($this->segs as $seg)
			$cnt += $seg->trkPts();
		return $cnt;
	}
	public function getGpxBounds() { 
		$gtxBounds;
		foreach ($this->segs as $seg) {
			if (! isset($gtxBounds))
				$gtxBounds = GpxBounds::initWithBounds($seg->getGpxBounds());
			else
				$gtxBounds->addBounds($seg->getGpxBounds());
		}

		return $gtxBounds;
	}
	
	public function getLength($unit = 'km') {
		$length = 0;
		foreach ($this->segs as $seg) {
			$length += $seg->getLength();
		}
		
		switch ($unit) {
				case 'm':
					$length *= 1000;
					break;
		}
		
		return $length;
	}

	public function getTime() {
		$time = "";

		if (count($this->segs) > 0)
			$time = $this->segs[0]->getFirstTime();
		
		return $time;
	}
	public function getLastTime() {
		$time = "";

		if (count($this->segs) > 0)
			$time = $this->segs[count($this->segs) - 1]->getLastTime();
		
		return $time;
	}
	
	public function getDuration() { 
		$duration = 0;
		foreach ($this->segs as $seg)
			$duration += $seg->getDuration();
		
		return $duration;
	}

}

class GpxTrkSeg {
	private ?GpxBounds $gpxBounds;
	private int $pts = 0;
	private float $segLength = 0;
	private ?SimpleXMLElement $segNode;
	private DateTime $firstDate, $lastDate;

	public function __construct(SimpleXMLElement $seg) {
		$this->segNode = $seg;
		$lastPt;

		foreach ($seg->trkpt as $pt) {
			if (($this->pts += 1) == 1) {
				$this->gpxBounds = GpxBounds::initWithValues($pt['lon'], $pt['lat']);
				$this->firstDate = new DateTime($pt->time);
			} else {
				$this->gpxBounds->addValues($pt['lon'],  $pt['lat']);
				
				$this->segLength += GpsTools::distance(floatval($lastPt['lon']), floatval($lastPt['lat']), floatval($pt['lon']), floatval($pt['lat']));
			}
			
			$lastPt = $pt;
		}
		$this->lastDate = new DateTime($lastPt->time);
	}

	public function trkPts() { return $this->pts; }
	public function getGpxBounds() { return $this->gpxBounds; }
	public function getLength() { return $this->segLength; }
	public function getFirstTime() { return $this->firstDate->format('Y-m-d\TH:i:s\Z'); }
	public function getLastTime() { return $this->lastDate->format('Y-m-d\TH:i:s\Z'); }
	public function getDuration() { $diff = $this->lastDate->diff($this->firstDate); return $diff->d * 86400 + $diff->h * 3600 + $diff->i * 60 + $diff->s; }
}

class GpsTools {
	public static function distance(float $lon1, float $lat1, float $lon2, float $lat2, $decimals = 2) {
		if ($lon1 == $lon2 && $lat1 == $lat2)
			return 0;
	        // Calculate the distance in degrees
        	$degrees = rad2deg(
			acos(
				(sin(deg2rad($lat1)) * sin(deg2rad($lat2))) + 
				(cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon1 - $lon2)))
			)
		);

		// 1 degree = 111.13384 km, based on the average diameter of the Earth (12,735 km)
	        return round($degrees * 111.13384, $decimals);
	}
}

function formatDuration($duration) {
	$temp = "";
	if (($d=(int)($duration / 86400)) > 0) {
		$duration %= 86400;
		$temp = $d . " Tag" . ($d > 1 ? "e" : "") . " ";
	}
	$h = (int)($duration / 3600);
	$duration %= 3600;
	$m = (int)($duration / 60);
	$s = $duration % 60;

	return $temp . sprintf('%02d:%02d.%02d', $h, $m, $s);;
}

function formatDate($time) {
	return date('d.m.Y H:i:s T', $time);
}

function convDate($dateStr) {
	return formatDate(strtotime($dateStr));
}
