<?php
	class TiltOMS {
		private $WorkspaceId;
		private $WorkspaceIdParts;
		private $PrimaryKey;
		private $LogType;
		private $SendMethod = "POST";
		private $SendContentType = "application/json";
		private $SendResource = "/api/logs";
		private $SendApiVersion = "2016-04-01";
		private $TimeStampField = "";
		
		public function __Construct() {}
		
		public function withAzureInfo($Id, $Key) {
			$instance = new self();
			$a = $instance->Set_Workspace_Id($Id);
			$b = $instance->Set_Primary_Key($Key);
			return ($a and $b);
		}
		
		public function Set_Workspace_Id($val) {
            //Verify the Workspace ID is a GUID (8-4-4-4-12) encoded string
			if (preg_match("/([0-9a-f]{8})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{4})-([0-9a-f]{12})/i", $val, $this->WorkspaceIdParts)) {
				$this->WorkspaceId = $val;
				return true;
			} else {
				return false;
			}
			
		}
		
		public function Set_Primary_Key($val) {
            //Verify the Primary Key is a base64 encoded string
			if (preg_match("/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/", $val)) {
				$this->PrimaryKey = $val;
				return true;
			} else {
				return false;
			}
		}
		
		public function Set_Log_Type($val) {
            //Ensure there are no spaces in the LogType Name
			if (preg_match("/^([^\ ]*)$/", $val)) {
				$this->LogType = $val;
				return true;
			} else {
				return false;
			}
		}
		
		public function Set_Timestamp_Field($Field) {
            //Make sure the Timestamp field we want to use is actually in the POST data.
			foreach ($_POST as $key=>$value) {
				if ($key == $Field) {
					$this->TimeStampField = $Field;
					return true;
				}
			}
			return false;
		}
		
		private function Convert_Excel_Time($val) {
            //Converts an Excel Time Value to a DateTime variable
			$date = DateTime::createFromFormat('Y-m-d H:i:s', '1899-12-30 00:00:00');
			$excelSeconds = floor($val * 86400);
			$date->add(new DateInterval("PT" . $excelSeconds . "S"));
			return $date;
		}
		
		public function Generate_Entry($ExcelTime, $Temp, $SG, $Beer, $Color, $Comment='None') {
            //Generate a Timestamp and form the JSON for the entry
			$TimeStamp = gmdate('Y-m-d\TH:i:s.v\Z');
			$Entry = "[{\"Timepoint\": \"{$TimeStamp}\", \"Temp\": {$Temp}, \"SG\": {$SG}, \"Beer\": \"{$Beer}\", \"Color\": \"{$Color}\", \"Comment\": \"{$Comment}\",\"Source\": \"TiltPHP\"}]";
			return $Entry;
		}
		
		public function Generate_Entry_From_Post($PostArr) {
			if (is_array($PostArr)) {
				$ExcelTime = $_POST['Timepoint'];
				$Temp = $_POST['Temp'];
				$SG = $_POST['SG'];
				$Beer = $_POST['Beer'];
				$Color = $_POST['Color'];
                $Comment = $_POST['Comment'];
                //Todo: Cleanup Excel Time
				return $this->Generate_Entry(0, $Temp, $SG, $Beer, $Color, $Comment);
			}
		}
		
		public function Send_Log_Analytics_Data($Payload) {
			#Get current timestamp (UTC) and Payload length
			$Rfc1123Date = gmdate('D, d M Y H:i:s T');
			$PayloadLength = strlen($Payload);
			
			#Generate the Signature
			$StringToSign = "{$this->SendMethod}\n{$PayloadLength}\n{$this->SendContentType}\nx-ms-date:{$Rfc1123Date}\n{$this->SendResource}";
			$Utf8String = utf8_encode($StringToSign);
			$Hash = hash_hmac('sha256', $Utf8String, base64_decode($this->PrimaryKey), true);
			$EncodedHash = base64_encode($Hash);
			$Signature = "SharedKey {$this->WorkspaceId}:{$EncodedHash}";
			
			#Build the URI and headers for cURL
			$Uri = "https://{$this->WorkspaceId}.ods.opinsights.azure.com{$this->SendResource}?api-version={$this->SendApiVersion}";
			$Handle = curl_init($Uri);
			$Headers = array("Authorization: {$Signature}", "Log-Type: {$this->LogType}", "x-ms-date: {$Rfc1123Date}", "time-generated-field: {$this->TimeStampField}", "Content-Type: {$this->SendContentType}");
			
			#Set cURL Options
			curl_setopt($Handle, CURLOPT_POST, true);
			curl_setopt($Handle, CURLOPT_HEADER, true) or die("Failed to set cURL Header");
			curl_setopt($Handle, CURLOPT_HTTPHEADER, $Headers) or die("Failed to set cURL Header");
			curl_setopt($Handle, CURLOPT_POSTFIELDS, $Payload);
			curl_setopt($Handle, CURLOPT_RETURNTRANSFER, true) or die("Failed to set cURL Return Transfer");
			
			#Execute cURL and return the result
            $Result = curl_exec($Handle);
            $CurlInfo = curl_getinfo($Handle, CURLINFO_HTTP_CODE);
            $fh = fopen('./log', 'a+');
            fwrite($fh, $Payload . "\n");
            fwrite($fh, $Result . "\n");
            fwrite($fh, $CurlInfo . "\n");
            fclose($fh);
            
            return $CurlInfo;
		}		
	}
?>
