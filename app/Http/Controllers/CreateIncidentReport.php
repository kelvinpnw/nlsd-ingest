<?php

namespace App\Http\Controllers;


use App\Port;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use DB;
use App\Quotation;

//Models
use App\Reporter;
use App\Incident;

class CreateIncidentReport extends Controller
{
    public function main(Request $request)
    {



        //Decode log line
        $logline = base64_decode($request['log']);

        //Find Source IP
        $sourceip = $this->FindVariable($logline, 'SRC=');
        $protocol = $this->FindVariable($logline, 'PROTO=');
        $whitelisted_protocols = array('ICMPv6', '2', '1', 'ICMP');

        if ($this->ValidateKey($request['key'])) {

            if (!in_array($protocol, $whitelisted_protocols)) {
                //Date to search for past 24 hours
                $date_24h = new \DateTime();
                $date_24h->modify('-24 hours');
                $formatted_date_24h = $date_24h->format('Y-m-d H:i:s');

                //Date to search for past 4 hours
                $date_4h = new \DateTime();
                $date_4h->modify('-4 hours');
                $formatted_date_4h = $date_4h->format('Y-m-d H:i:s');


                $fourhour_recently_logged = Incident::where('created_at', '>=', $formatted_date_4h)->where('sourceip', '=', $sourceip)->get();
                $fourhour_recently_reported = Incident::where('created_at', '>=', $formatted_date_4h)->where('sourceip', '=', $sourceip)->where('reportfiled', '=', 1)->get();

                $day_recently_logged = Incident::where('created_at', '>=', $formatted_date_24h)->where('sourceip', '=', $sourceip)->get();
//                $day_recently_reported = Incident::where('created_at', '>=', $formatted_date_24h)->where('sourceip', '=', $sourceip)->where('reportfiled', '=', 1)->get();


                //Create new log object
                $incident = new Incident;
                //TODO: make this the actual reporter ID based on the key
                $incident->reporter_id = Reporter::where('Key', '=', $request['key'])->first()->id;;
                $incident->sourceip = $this->FindVariable($logline, 'SRC=');
                $incident->destinationip = $this->FindVariable($logline, 'DST=');
                $incident->protocol = $this->FindVariable($logline, 'PROTO=');
                $incident->sourceport = $this->FindVariable($logline, 'SPT=');
                $incident->destport = $this->FindVariable($logline, 'DPT=');


                //Create INET_ATON Entry based on IP version
                if (filter_var($sourceip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    //if ipv4 use inetaton
                    $incident->inet_sourceip = $this->getINET_ATONvalue($sourceip)->inet;

                } elseif (filter_var($sourceip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    //if ipv6 use inet6aton
                    $incident->inet6_sourceip = $this->getINET6_ATONvalue($sourceip)->inet6;
                } else {
                    //something is fucky with the submitted address, kill this now.
                    abort(400);
                }
                $incident->line = $logline;
                //Meets criteria to be reported
                if ($fourhour_recently_logged->count() >= 5 && $fourhour_recently_reported->count() == 0) {
                    echo "MEETS REPORT CRITERIA";


                    $incidents_past_4hours = $fourhour_recently_logged->count();
                    $incidents_past_24hours = $day_recently_logged->count();
                    $incidents_alltime = Incident::where('sourceip', '=', $sourceip)->count();

//                $unique_target_ports = $fourhour_recently_logged->unique('destport')->count();
                    $unique_target_hosts = $fourhour_recently_logged->unique('destinationip')->count();
                    $target_port_list = $fourhour_recently_logged->unique('destport')->implode('destport', ',');


                    //Generate AbuseIPDB categories, right now it's only 14 because this software's scope is limited to port scans.
                    $categories = array('14');
                    if ($this->isIoTTargetted($fourhour_recently_logged) === true) {
                        $categories = array_merge($categories, array('20', '23'));
                    }

                    //Generate AbuseIPDB report message
                    $message = $sourceip . ' was recorded ' . $incidents_past_4hours . ' times by ' . $unique_target_hosts . ' hosts attempting to connect to the following ports: ' . $target_port_list . '. Incident counter (4h, 24h, all-time): ' . $incidents_past_4hours . ', ' . $incidents_past_24hours . ', ' . $incidents_alltime;
                    //Submit to AbuseIPDB
                    $report_result = $this->AbuseIPDB_Submit($sourceip, $message, $categories);
                    if ($report_result)
                        $incident->reportfiled = 1; //indicates a report was made successfully
                    else
                        $incident->reportfiled = 3; //report status 3 indicates an exception


                } elseif ($fourhour_recently_reported->count() == 1) { //only log if this has been reported 6 times in 24 hours
                    echo "LOGGING ONLY; EXCEEDED 4H THRESHOLD";
                    $incident->reportfiled = 2; //2 is the report status for an incident that was not reported due to exceeding the 24h threshold

                } else {
                    //create a log if it has not yet reached 5 entries in the past 4 hours.
                    $incident->reportfiled = 0; //indicates no report was made for this incident
                    echo "LOGGING ONLY; BELOW THRESHOLD";
                }


                $incident->save();

            } else {
                echo "BLACKLISTED PROTOCOL, IGNORING.";
            }
            //echo "OK";
        } else {
            //Abort if the key is invalid.
            abort('403');
        }
        return;

    }

    private function FindVariable($haystack, $needle)
    {
        //Find the position where the variable shows up
        $var_start = strpos($haystack, $needle);
        //Get the portion of the string from there to the end
        $var_to_eos = substr($haystack, $var_start);
        //Find first space after variable
        $var_end = strpos($var_to_eos, ' ');
        //Get the string from the beginning to the end of the variable
        $var_with_name = substr($var_to_eos, 0, $var_end);
        //remove the variable name from the string
        $result = str_replace($needle, '', $var_with_name);
        return $result;

    }

    private function AbuseIPDB_Submit($ip, $message, $categories)
    {
        $report['key'] = env('ABUSEIPDB');
//        echo $report['key'];
        $report['category'] = implode(",", $categories);
        $report['comment'] = $message;
        $report['ip'] = $ip;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.abuseipdb.com/report/json');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($report));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $reply = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err)
            return false;
        else
            return true;
    }

    public function ValidateKey($key)
    {
        $result = Reporter::where('Key', '=', $key)->first();
        if ($result)
            return true;
        else
            return false;
    }


    //Is this an IoT Targetted Attack? Check recent attacks against a list of ports and protcols commonly used for IoT Attacks
    public function isIoTTargetted($targets)
    {
        $IoT_Targets = Port::select('protocol', 'destport')->where('isIoTTarget', '=', '1')->get();
        foreach ($targets as $target) {
            foreach ($IoT_Targets as $IoT_Target) {
                if (($IoT_Target->destport == $target->destport) && $IoT_Target->protocol == $target->protocol)
                    return true;
            }
        }
        return false;
    }
    private function getINET6_ATONvalue($ip)
    {
        return DB::select('SELECT INET6_ATON(?) AS `inet6`',[$ip])[0];
    }
    private function getINET_ATONvalue($ip)
    {
        return DB::select('SELECT INET_ATON(?) AS `inet`',[$ip])[0];
    }
}
