<?php 

	/**
	* 
	*/
	class Db_table
	{
		private $conn;
		private $tablename = "";
		private $gen_insert_query;
		private $gen_data_array;
		private $gen_update_query;
		private $gen_update_data_array;

		function __construct($table_name)
		{
			require_once dirname(__FILE__) . '/../../include/DbConnect.php';
            // opening db connection
            $db         = new DbConnect();
            $this->conn = $db->PDO();
			$this->tablename = $table_name;

            $res = $this->conn->query("SHOW COLUMNS FROM ".$this->tablename)->fetchAll();

            $this->getInsertQuery($res);
		    $this->getUpdateQuery($res);
		}

		public function isInserted($fm_id)
		{
			$res = $this->conn->query("SELECT fm_caid FROM ".$this->tablename. " WHERE fm_id = " . $fm_id)->fetchAll();

			if(sizeof($res) > 0){
				return true;
			}
			return false;
		}

		private function getInsertQuery($array)
		{

	        $q = "INSERT INTO ";
	        $q .= "`" . $this->tablename . "` (";

	        $cols = '';
	        $vals = '';
	        $para = '';
	        $data_val = '';
	        $data_arr = [];

	        foreach ($array as $field) {
	            if($field['Extra'] != "auto_increment"){
	                $cols .= "`" . $field['Field'] . "`, ";
	                $vals .= ":".$field['Field'].", ";

	                if (strpos($field['Type'], 'int') !== false) {
	                    $para .= 'i';
	                }else{
	                    $para .= 's';
	                }

	                $data_arr[] = $field['Field'];
	            }
	        }


	        $cols     = substr(trim($cols), 0, -1);
	        $vals     = substr(trim($vals), 0, -1);

	        $q .= $cols . ") VALUES(";
	        $q .= $vals . ")";

	        $this->gen_insert_query = $q;
	        $this->gen_data_array = $data_arr;
	        // $this->gen_insert_param = $para;
	    }

	    private function getUpdateQuery($array)
	    {
	        
	        $q = "UPDATE ";
	        $q .= "`" . $this->tablename . "` SET ";

	        $cols = '';
	        $data_arr = [];

	        foreach ($array as $field) {
	            if($field['Extra'] != "auto_increment" && $field['Field'] != "fm_id"){
	                $cols .= "" . $field['Field'] . " = :".$field['Field'].", ";

	                $data_arr[] = $field['Field'];
	            }
	        }

	        $cols = substr(trim($cols), 0, -1);

	        $q .= $cols ;
	        $q .= " WHERE fm_id = :fm_id";

			$this->gen_update_query = $q;
	        $this->gen_update_data_array = $data_arr;
	    }

		public function create($data)
        {
        	$final_data = [];
            $data_array = $this->gen_data_array;
            foreach($data_array as $val){
                if(!isset($data[$val])){
                    $data[$val] = '';
                }
                $final_data[$val] = $data[$val];
            }

            $query = $this->gen_insert_query;

            $stmt = $this->conn->prepare($query);

            $result = $stmt->execute($final_data);
            // Check for successful insertion
            if ($result) {
                // Farmer successfully inserted
                return true;
            } else {
                // Failed to create Farmer
                return false;
            }
        }

        public function update($data)
        {
        	$final_data = [];
            $data_array = $this->gen_update_data_array;
            foreach($data_array as $val){
                if(!isset($data[$val])){
                    $data[$val] = '';
                }
                $final_data[$val] = $data[$val];
            }

            //setting explicitly because its not exists in $data_array
            $final_data['fm_id'] = $data['fm_id'];

            $query = $this->gen_update_query;
            $stmt = $this->conn->prepare($query);


            $result = $stmt->execute($final_data);

            // Check for successful update
            if ($result) {
                // Data successfully updated
                return true;
            } else {
                // Failed to update Data
                return false;
            } 
        }
	}

	$app->post('/send_table', 'authenticate', function() use ($app){
        verifyRequiredParams(['tablename', 'fm_id']); //provide a list of required parametes
        
        //declare variables
        $data = $app->request->post(); //fetching the post data into variable
        $err_data = [];
        global $user_id;
    	$tablename = $data['tablename'];

    	$tbl_arr = array('tbl_personal_detail','tbl_financial_details','tbl_farmers','tbl_applicant_knowledge','tbl_applicant_phone','tbl_asset_details','tbl_appliances_details','tbl_cultivation_data','tbl_family_details','tbl_land_details','tbl_livestock_details','tbl_loan_details','tbl_residence_details','tbl_spouse_details','tbl_spouse_knowledge','tbl_yield_details');

    	if(!in_array($tablename,$tbl_arr))//check valid table
    	{
    		$err_data = [
            	["error_code" => "404", "error_message" => "Invalid table name"]
            ];
    	}


        //set default values here
        $data['fm_caid'] = $user_id;


     	//check if validation errors exists
        if($err_data !== []){
        	$response["success"] = false;
            $response["data"] = $err_data;
            echoResponse(201, $response);
        }else{

        	$db = new Db_table($tablename);
        	//valid data hence inserting/updating into table
        	if($db->isInserted($data['fm_id'])){

				if($tablename!='tbl_farmers')
		        {
		        	$data['f_modified_date'] = date('Y-m-d h:i:s');
		        	$data['f_modified_by']   = $user_id;
		        }

		        $return_data = $db->update($data);
        	}
        	else{
		          $return_data = $db->create($data);
		          if($tablename!='tbl_farmers')
		      	  {
		        	$data['f_created_date'] = date('Y-m-d h:i:s');
		        	$data['f_created_by']   = $user_id;
		       	 }
        	}

	        if ($return_data !== false) {
	            $response["success"] = true;
	            $response["data"] = ["message" => "Data added/updated successfully!"];
	        } else {
	            $response["success"] = false;
	            $response["data"] = [
	            	["error_code" => "103", "error_message" => "Data could not be add/update."]
	            ];
	            echoResponse(201, $response);
	        }
	        echoResponse(201, $response);
        }
    });