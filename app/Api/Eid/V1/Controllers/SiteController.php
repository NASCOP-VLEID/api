<?php

namespace App\Api\Eid\V1\Controllers;

use Illuminate\Http\Request;
use App\Facility;
use App\Api\Eid\V1\Controllers\BaseController;

use DB;

class SiteController extends BaseController
{
    //

    private function set_key($site){
    	if(is_numeric($site)){
			return "facilitys.facilitycode"; 
		}
		else{
			return "facilitys.DHIScode";
		}
    }


	public function unsupported_sites(){
    	$raw = '';

    	return DB::table('facilitys')
			->select('facilitys.ID as facility_id',
			'facilitys.facilitycode as FacilityMFLCode',
			'facilitys.DHIScode as FacilityDHISCode',
			'facilitys.name as Facility', 
			'districts.ID as subcounty_id',
			'districts.SubCountyDHISCode as SubCountyDHISCode', 
			'districts.SubCountyMFLCode as SubCountyMFLCode', 
			'districts.name as subcounty', 
			'countys.ID as county_id', 
			'countys.CountyDHISCode as CountyDHISCode', 
			'countys.CountyMFLCode as CountyMFLCode',
			'countys.name as county')
			->join('districts', 'districts.ID', '=', 'facilitys.district')
			->join('countys', 'countys.ID', '=', 'districts.county')
			->where('facilitys.partner', 0)
			->get();
    }


    public function sites(){
    	return DB::table('facilitys')
    	->select(DB::raw($this->site_string . 'longitude, latitude'))->orderBy('ID')
    	->get();
    }

    public function info($site){
    	$raw = '';

    	return DB::table('facilitys')
			->select('facilitys.ID as facility_id', 'facilitys.facilitycode as facilityMFLCode', 'facilitys.DHIScode as facilityDHISCode', 'facilitys.name as site', 'districts.ID as subcounty_id', 'districts.name as subcounty', 'countys.ID as county_id', 'countys.name as county', 'partners.ID as partner_id', 'partners.name as partner')
			->join('districts', 'districts.ID', '=', 'facilitys.district')
			->join('countys', 'countys.ID', '=', 'districts.county')
			->join('partners', 'partners.ID', '=', 'facilitys.partner')
			->where('facilitys.ID', $site)
			->orWhere('facilitycode', $site)
			->orWhere('DHIScode', $site)
			->get();
    }

    public function summary($site, $type, $year, $month=NULL, $year2=NULL, $month2=NULL){

		$data = NULL;

		$raw = $this->site_string . $this->summary_query();

		$key = $this->set_key($site);
		

		// Totals for the whole year
		if($type == 1){

			$data = DB::table('site_summary')
			->select('year', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site, $key){
				if($site != "0" || $site != 0){
					return $query->where($key, $site);
				}
				else{
					return $query->orderBy('all_tests')->limit(100);
				}					
			})
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();

		}

		// For the whole year but has per month
		else if($type == 2){
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site, $key){
				if($site != "0" || $site != 0){
					return $query->where($key, $site);
				}
				else{
					return $query->orderBy('all_tests')->limit(100);
				}					
			})
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular month
		else if($type == 3){

			if($month < 1 || $month > 12) return $this->invalid_month($month);
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site, $key){
				if($site != "0" || $site != 0){
					return $query->where($key, $site);
				}
				else{
					return $query->orderBy('all_tests')->limit(100);
				}					
			})
			->where('month', $month)
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular quarter
		// The month value will be used as the quarter value
		else if($type == 4){

			if($month < 1 || $month > 4) return $this->invalid_quarter($month);
			
			$my_range = $this->quarter_range($month);
			$lesser = $my_range[0];
			$greater = $my_range[1];

			$d = DB::table('site_summary')
			->select('year', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site, $key){
				if($site != "0" || $site != 0){
					return $query->where($key, $site);
				}
				else{
					return $query->orderBy('all_tests')->limit(100);
				}					
			})
			->where('month', '>', $lesser)
			->where('month', '<', $greater)
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
			
			$a = "Q" . $month;
			$b = $this->quarter_description($month);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Quarter'] = $a;
				$data[$i]['Period'] = $b;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
		}

		// For Multiple Months across years
		else if($type == 5){

			if($year > $year2) return $this->pass_error('From year is greater');
			if($year == $year2 && $month >= $month2) return $this->pass_error('From month is greater');

			$q = $this->multiple_year($year, $month, $year2, $month2);
			// return $this->pass_error($q);

			if($year == $year2 && $month < $month2){
				$d = DB::table('site_summary')
				->select('year', DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->where('year', $year)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->whereBetween('month', [$month, $month2])
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode', 'year')
				->get();
			}

			if($year < $year2){
				$d = DB::table('site_summary')
				->select( DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->whereRaw($q)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode')
				->get();

				
			}
			$desc = $this->describe_multiple($year, $month, $year2, $month2);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Period'] = $desc;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
			
		}

		// Else an invalid type has been specified
		else{
			return $this->invalid_type($type);
		}

		
		return $this->check_data($data);

	}

	public function hei_outcomes($site, $type, $year, $month=NULL, $year2=NULL, $month2=NULL){

		$data = NULL;

		$raw = $this->site_string . $this->hei_outcomes_query();
		
		$key = $this->set_key($site);

		// Totals for the whole year
		if($type == 1){

			$data = DB::table('site_summary')
			->select('year', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('lost_to_follow_up')->limit(100);
				}
			})
			->where('year', $year)
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();

		}

		// For the whole year but has per month
		else if($type == 2){
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('lost_to_follow_up')->limit(100);
				}
			})
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular month
		else if($type == 3){

			if($month < 1 || $month > 12) return $this->invalid_month($month);
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('lost_to_follow_up')->limit(100);
				}
			})
			->where('month', $month)
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular quarter
		// The month value will be used as the quarter value
		else if($type == 4){

			if($month < 1 || $month > 4) return $this->invalid_quarter($month);
			
			$my_range = $this->quarter_range($month);
			$lesser = $my_range[0];
			$greater = $my_range[1];

			$d = DB::table('site_summary')
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->select('year', DB::raw($raw))
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('lost_to_follow_up')->limit(100);
				}
			})
			->where('month', '>', $lesser)
			->where('month', '<', $greater)
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
			
			$a = "Q" . $month;
			$b = $this->quarter_description($month);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Quarter'] = $a;
				$data[$i]['Period'] = $b;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
		}

		// For Multiple Months across years
		else if($type == 5){

			if($year > $year2) return $this->pass_error('From year is greater');
			if($year == $year2 && $month >= $month2) return $this->pass_error('From month is greater');

			$q = $this->multiple_year($year, $month, $year2, $month2);
			// return $this->pass_error($q);

			if($year == $year2 && $month < $month2){
				$d = DB::table('site_summary')
				->select('year', DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->where('year', $year)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->whereBetween('month', [$month, $month2])
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode', 'year')
				->get();
			}

			if($year < $year2){
				$d = DB::table('site_summary')
				->select( DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->whereRaw($q)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode')
				->get();

				
			}
			$desc = $this->describe_multiple($year, $month, $year2, $month2);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Period'] = $desc;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
			
		}

		// Else an invalid type has been specified
		else{
			return $this->invalid_type($type);
		}

		
		return $this->check_data($data);

	}

	public function hei_validation($site, $type, $year, $month=NULL, $year2=NULL, $month2=NULL){

		$data = NULL;

		$raw = $this->site_string . $this->hei_validation_query();
		
		$key = $this->set_key($site);

		// Totals for the whole year
		if($type == 1){

			$data = DB::table('site_summary')
			->select('year', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('confirmed_pos')->limit(100);
				}
			})
			->where('year', $year)
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();

		}

		// For the whole year but has per month
		else if($type == 2){
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('confirmed_pos')->limit(100);
				}
			})
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular month
		else if($type == 3){

			if($month < 1 || $month > 12) return $this->invalid_month($month);
			$data = DB::table('site_summary')
			->select('year', 'month', DB::raw($raw))
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('confirmed_pos')->limit(100);
				}
			})
			->where('month', $month)
			->groupBy('month')
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
		}

		// For a particular quarter
		// The month value will be used as the quarter value
		else if($type == 4){

			if($month < 1 || $month > 4) return $this->invalid_quarter($month);
			
			$my_range = $this->quarter_range($month);
			$lesser = $my_range[0];
			$greater = $my_range[1];

			$d = DB::table('site_summary')
			->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
			->select('year', DB::raw($raw))
			->where('year', $year)
			->when($site, function($query) use ($site){
				if($site != 0) return $query->where('facilitys.ID', $site);

				else{
					return $query->orderBy('confirmed_pos')->limit(100);
				}
			})
			->where('month', '>', $lesser)
			->where('month', '<', $greater)
			->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.facilitycode', 'facilitys.DHIScode', 'year')
			->get();
			
			$a = "Q" . $month;
			$b = $this->quarter_description($month);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Quarter'] = $a;
				$data[$i]['Period'] = $b;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
		}

		// For Multiple Months across years
		else if($type == 5){

			if($year > $year2) return $this->pass_error('From year is greater');
			if($year == $year2 && $month >= $month2) return $this->pass_error('From month is greater');

			$q = $this->multiple_year($year, $month, $year2, $month2);
			// return $this->pass_error($q);

			if($year == $year2 && $month < $month2){
				$d = DB::table('site_summary')
				->select('year', DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->where('year', $year)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->whereBetween('month', [$month, $month2])
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode', 'year')
				->get();
			}

			if($year < $year2){
				$d = DB::table('site_summary')
				->select( DB::raw($raw))
				->leftJoin('facilitys', 'facilitys.ID', '=', 'site_summary.facility')
				->whereRaw($q)
				->when($county, function($query) use ($county, $key){
					if($county != "0" || $county != 0){
						return $query->where($key, $county);
					}
				})
				->groupBy('facilitys.ID', 'facilitys.name', 'facilitys.CountyDHISCode', 'facilitys.CountyMFLCode')
				->get();

				
			}
			$desc = $this->describe_multiple($year, $month, $year2, $month2);

			for ($i=0; $i < sizeof($d); $i++) { 
				$data[$i]['Period'] = $desc;
				foreach ($d[$i] as $obj_prop => $ob_val) {
					$data[$i][$obj_prop] = $ob_val;
				}
			}
			
		}

		// Else an invalid type has been specified
		else{
			return $this->invalid_type($type);
		}

		
		return $this->check_data($data);

	}

	
}