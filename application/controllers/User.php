<?php
defined('BASEPATH') or exit('No direct script access allowed');

class User extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('user_model');
        $this->load->helper(array('form', 'url'));
        $this->load->library('form_validation');
        $this->load->library('session');
        //$this->load->library('javascript');

        //get all users
        $this->data['users'] = $this->user_model->getAllUsers();
    }

    public function index()
    {
        if ($this->session->userdata('userusername')) {
            redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
        } else {
            $this->load->view("index");
        }
    }

    public function register()
    {
        $this->load->view('register', $this->data);
    }

    public function navbar()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data['records'] = $this->user_model->fetch_all_service();
        $this->load->view("navbar", $data);
    }

    public function footer()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data['records'] = $this->user_model->fetch_all_service();
        $this->load->view("footer", $data);
    }

    public function dob_check($str) {
        $tz  = new DateTimeZone('Asia/Manila');
        //echo $str;
        $age = DateTime::createFromFormat('Y-m-d', $str, $tz)
            ->diff(new DateTime('now', $tz))
            ->y;

        if ($age < 18) { //yes it's YYYY-MM-DD
            $this->form_validation->set_message('dob_check', '{field} must be 18 and above.');
            return FALSE;
        } else {
            return TRUE;
        }
    }

    public function register_data()
    {
        $this->form_validation->set_message('is_unique', 'The %s is already taken.');
        $this->form_validation->set_rules('fname', 'First name', 'required');
        $this->form_validation->set_rules('lname', 'Last name', 'required');
        $this->form_validation->set_rules('username', 'Username', 'required|min_length[4]|max_length[30]|is_unique[users.users_username]');
        $this->form_validation->set_rules('email', 'Email', 'valid_email|is_unique[users.users_email]|required');
        $this->form_validation->set_rules('password', 'Password', 'required|min_length[6]|max_length[30]');
        $this->form_validation->set_rules('password_confirm', 'Confirm Password', 'required|matches[password]');
        $this->form_validation->set_rules('birthdate', 'Age', 'required|callback_dob_check');

        if ($this->form_validation->run() == FALSE) {
            $this->load->view('register', $this->data);
        } else {
            $config['allowed_types'] = 'jpg|png';
            $config['upload_path'] = './uploads/';
            $config['encrypt_name'] = true;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('image')) {
                $acc = $this->input->post('account');

                $name = $this->input->post('fname') . " " . $this->input->post('lname');
                $user_avatar = $this->upload->data('file_name');
                //generate simple random code
                $set = '123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $code = substr(str_shuffle($set), 0, 12);
                $user = array(
                    'users_account' => $this->input->post('account'),
                    'users_avatar' => $user_avatar,
                    'users_name' => $name,
                    'users_username' => $this->input->post('username'),
                    'users_birthdate' => $this->input->post('birthdate'),
                    'users_email' => $this->input->post('email'),
                    'users_password' => $this->input->post('password'),
                    'users_code' => $code,
                    'users_active' => false,
                    'users_wallet' => 0
                );

                $id = $this->user_model->insert($user);

                $tz  = new DateTimeZone('Asia/Manila');
                $age = DateTime::createFromFormat('Y-m-d', $this->input->post('birthdate'), $tz)
                    ->diff(new DateTime('now', $tz))
                    ->y;

                $traineedetails = array(
                    'Age' => $age,
                    'Height' => $this->input->post('Height'),
                    'Weight' => $this->input->post('Weight'),
                    'Health' => $this->input->post('Health'),
                    'BMI' => $this->input->post('BMI'),
                    'ID' => $id
                );


                //if trainee ung acc
                if ($acc == "Trainee") {
                    $this->user_model->trainee($traineedetails);
                }
                //if coach ung acc
                else if ($acc == "Coach") {
                    if ($this->upload->do_upload('req')) {
                        $coachdetails = array(
                            'Age' => $age,
                            'Requirement' => $this->upload->data('file_name'),
                            'ID' => $id
                        );
                        $this->user_model->coach($coachdetails);
                    }
                }

                $message =     "
                            <html>
                            <head>
                                <title>Verification Code</title>
                            </head>
                            <body>
                                <h2>Thank you for Registering.</h2>
                                <p>Your Account:</p>
                                <p>Email: " . $user['users_email'] . "</p>
                                <p>Username: ". $user['users_username'] . " </p>
                                <p>Password: ". $user['users_password'] . " </p>
                                <br>
                                <p>Note: When you forget your password, kindly visit this email message. Do not share any information displayed in this message.</p>
                                <br>
                                <p>Please click the link below to activate your account.</p>
                                <h4><a href='" . base_url() . "user/activate/" . $id . "/" . $user['users_code'] . "'>Activate My Account</a></h4>
                            </body>
                            </html>
                            ";


                $this->load->config('email');
                $this->load->library('email');
                $this->email->set_newline("\r\n");
                $this->email->from($config['smtp_user']);
                $this->email->to($user['users_email']);
                $this->email->subject('Signup Verification Email');
                $this->email->message($message);

                //sending email
                if($acc == "Trainee") {
                    if ($this->email->send()) {
                        $this->session->set_flashdata('message', 'Activation code sent to email');
                        $this->session->set_flashdata('username', $user['users_username']);
                        $this->session->set_flashdata('name', $user['users_name']);
                        if($acc == "Trainee") {
                            $this->session->set_flashdata('account', 'traineerole');
                        }
                    } else {
                        $this->session->set_flashdata('message', 'Something went wrong. Please try again');
                    }
                } else {
                    $this->session->set_flashdata('message', 'Account created! Please wait for the admin to verify your requirements.');
                    $this->session->set_flashdata('username', $user['users_username']);
                    $this->session->set_flashdata('name', $user['users_name']);
                    if($acc == "Coach") {
                        $this->session->set_flashdata('account', 'coachrole');
                    }
                }
                
                redirect(base_url() . 'user/register');
            }
        }
    }

    public function activate()
    {
        $id =  $this->uri->segment(3);
        $code = $this->uri->segment(4);

        //fetch user details
        $user = $this->user_model->getUser($id);
        print_r($user);

        //if code matches
        if ($user['users_code'] == $code) {
            //update user active status
            $data['users_active'] = true;
            $query = $this->user_model->activate($data, $id);

            if ($query) {
                $this->session->set_flashdata('message', 'User activated successfully');
            } else {
                $this->session->set_flashdata('message', 'Something went wrong in activating account');
            }
        } else {
            $this->session->set_flashdata('message', 'Cannot activate account. Code didnt match');
        }

        redirect(base_url() . 'user/login');
    }

    public function login()
    {
        if ($this->session->userdata('userusername')) {
            redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
        } else {
            $this->load->view("login");
        }
    }

    public function logout()
    {
        $this->session->sess_destroy();
        redirect(base_url());
    }

    public function login_data()
    {
        $this->form_validation->set_rules('username', 'Username', 'required|callback_validation');
        $this->form_validation->set_rules('password', 'Password', 'required');
        if ($this->form_validation->run() == FALSE) {
            $this->login();
        } else {
            $check = $this->input->post('username');
            $data["users"] = $this->user_model->fetch_data($check);
            foreach ($data["users"] as $row) {
                if ($row->users_active == 0) {
                    $this->session->set_flashdata('message', 'Account is not activated. Please check your email.');
                    redirect('user/login');
                } else {
                    $this->session->set_userdata('account', $row->users_account);
                    $this->session->set_userdata('userusername', $row->users_username);
                    $this->session->set_userdata('userid', $row->users_id);
                    $this->session->set_userdata('username', $row->users_name);
                    $this->session->set_userdata('link', base_url() . 'user/profile/' . $this->session->userdata('userusername'));
                    $this->session->set_userdata('useravatar', $row->users_avatar);
                    $this->session->set_userdata('role', $row->users_id);
                    redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
                }
            }
        }
    }

    public function profile()
    {
        //$check = $this->session->userdata('userusername');
        $username = $this->uri->segment(3);
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data["services"] = $this->user_model->get_services($userid);
        $data["trainees"] = $this->user_model->get_trainees($username);
        $data["details"] = $this->user_model->get_traineedetails($userid);
        $data["coachdetails"] = $this->user_model->get_coachdetails($userid);

        $this->navbar();
        
        $this->load->view("userprofile", $data);
        if (!$this->session->userdata('userusername')) {
            redirect(base_url());
        }
        $this->footer();
    }

    public function editprofile()
    {
        $username = $this->uri->segment(3);
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $acc = $this->session->userdata('account');
        $data["services"] = $this->user_model->get_services($userid);
        $data["trainees"] = $this->user_model->get_trainees($username);
        $data["details"] = $this->user_model->get_traineedetails($userid);
        $data["coachdetails"] = $this->user_model->get_coachdetails($userid);

        $this->navbar();
        $this->load->view("edit_profile", $data);
        $this->footer();
    }

    public function bookings()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data["trainees"] = $this->user_model->get_trainees($username);
        $data["services"] = $this->user_model->fetch_service_by_userid($username);
        $data["services_coach"] = $this->user_model->fetch_service_by_userid_2($username);
        $data["services_of_coach"] = $this->user_model->fetch_all_services_of_coach($userid);
        //print_r($data["services_coach"][0]->services_duration);
        $this->navbar();
        $this->load->view("bookings", $data);
    }

    public function cancel_order() {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $this->user_model->delete_orders_by_id($id);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function cashout()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data["trainees"] = $this->user_model->get_trainees($username);
        if(isset($_GET['userid'])) {
			$userid=$_GET['userid'];
            $data["cashout"] = $this->user_model->get_cashout($userid);
		}
        $data["services"] = $this->user_model->fetch_service_by_userid($username);
        $data["services_coach"] = $this->user_model->fetch_service_by_userid_2($username);
        $this->navbar();
        $this->load->view("cashout", $data);
        $this->footer();
    }

    public function validation()
    {
        if ($this->user_model->log_in_correctly()) {
            return true;
        } else {
            $this->form_validation->set_message('validation', 'Incorrect username/password.');
            return false;
        }
    }

    public function validation_wallet()
    {
        if ($this->user_model->correct_amount_wallet()){
            return true;
        }
        else {
            $this->form_validation->set_message('validation', 'Error! Amount entered is more than your wallet balance. Try again.');
            return false;
        }
    }

    public function update_data()
    {
        $this->form_validation->set_rules('c_pass', 'Password', 'required|callback_password_validation');
        $this->form_validation->set_rules('n_pass', 'New password', 'required|min_length[8]');
        $this->form_validation->set_rules('r_pass', 'Confirm password', 'required|min_length[8]|matches[n_pass]');
        if ($this->form_validation->run() == FALSE) {
            $check = $this->session->userdata('userusername');
            $data["users"] = $this->user_model->fetch_data($check);
            $this->load->view("userprofile", $data);
        } else {
            $data = array(
                'users_username' => $this->session->userdata('userusername'),
                'users_password' => $this->input->post('n_pass')
            );
            $this->user_model->update_data($data);
            $check = $this->session->userdata('userusername');
            $data["users"] = $this->user_model->fetch_data($check);
            $this->session->set_flashdata('message', 'Password has been changed.');
            redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
            //$this->load->view("userprofile", $data);
        }
    }

    public function update_profile()
    {
        //$acc = $this->session->userdata('userusername');
        $acc = $this->session->userdata('account');
        $userid = $this->session->userdata('userid');

        $config['allowed_types'] = 'jpg|png';
        $config['upload_path'] = './uploads/';
        $config['encrypt_name'] = true;
        $this->load->library('upload', $config);

        echo $this->input->post('new_bmi');
        if ($acc == 'Trainee') {
            $newprofile = array(
                'Age' => $this->input->post('new_age'),
                'Height' => $this->input->post('new_height'),
                'Weight' => $this->input->post('new_weight'),
                'BMI' => $this->input->post('new_bmi'),
                'ID' => $this->session->userdata('userid'),
                'Health' => $this->input->post('new_health')
            );
            $this->user_model->update_traineeprofile($newprofile);
        } else if ($acc == 'Coach') {
            if ($this->upload->do_upload('new_req')) {
                $coach_req = $this->upload->data('file_name');
                $newcoachdetails = array(
                    'Age' => $this->input->post('new_age'),
                    'requirement' => $coach_req,
                    'ID' => $this->session->userdata('userid')
                );
                $this->user_model->update_coachprofile($newcoachdetails);
            } else {
                print_r($acc);
            }
        }

        $check = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($check);
        redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
    }

    /*public function update_description()
    {
        $acc = $this->session->userdata('account');
        $userid = $this->session->userdata('userid');

        $config['allowed_types'] = 'jpg|png';
        $config['upload_path'] = './uploads/';
        $config['encrypt_name'] = true;
        $this->load->library('upload', $config);

        if ($acc == 'Trainee') {
            $new_trainee_profile = $this->input->post('new_profile');
            $this->user_model->update_trainee_description($new_trainee_profile,$userid);
        }  
        else if ($acc == 'Coach') {
            $new_coach_profile = $this->input->post('new_profile');
            $this->user_model->update_coach_description($new_coach_profile,$userid);
        }

        $check = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($check);
        redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
    }*/
  
    public function password_validation()
    {
        if ($this->user_model->password_correct()) {
            return true;
        } else {
            $this->form_validation->set_message('password_validation', 'Current password is not the same with the old password.');
            return false;
        }
    }

    public function marketplace()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $data['records'] = $this->user_model->fetch_all_service();
        $data["details"] = $this->user_model->get_traineedetails($userid);
        $data['top_services'] = $this->user_model->get_services_by_sales();
        $this->navbar();
        $this->load->view("marketplace", $data);
        $this->footer();
    }

    public function create_cashout()
    {
        $data["users"] = $this->user_model->fetch_data($this->session->userdata('userusername'));
        foreach ($data["users"] as $row) {
            $wallet = $row->users_wallet;
            $email = $row->users_email;
            $userid = $row->users_id;
        }
        $data["cashout"] = $this->user_model->get_cashout($userid);
        date_default_timezone_set('Asia/Manila');
        $datetime = date('Y/m/d H:i:s');
        $cashout = array(
            'cashout_from' => $this->session->userdata('userusername'),
            'cashout_amount' => $wallet,
            'cashout_phone' => $this->input->post("phone"),
            'cashout_email' => $email,
            'cashout_datetime' => $datetime,
            'users_id' => $row->users_id,
            'cashout_remarks' => 0
        );


        $this->user_model->insert_cashout($cashout);
        $message = $this->load->view('email_confirm_cashout', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($email);
        $this->email->subject('Cashout Receipt');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        redirect(base_url() . 'user/cashout?userid='.$row->users_id);
    }

    public function add_service()
    {
        $workout_availability_temp = 1;
        $data["users"] = $this->user_model->fetch_data($this->session->userdata('userusername'));
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }

        $tempTimes = array();
        $temp["times"] = $this->user_model->fetch_workout_times($userid);
        //print_r($temp);

        foreach($temp["times"] as $time) {
            array_push($tempTimes, explode(" - ", $time->services_time));
        }

        //print_r($tempTimes);

        $arrDay = $this->input->post('workout_day');
        //print_r($arrDay);
        //echo implode(', ', $arrDay);

        //echo $this->input->post('workout_time_start');

        $timeStart = date('g:i A', strtotime($this->input->post('workout_time_start')));
        $timeEnd = date('g:i A', strtotime($this->input->post('workout_time_end')));

        $timeCheck = true;

        for($i = 0; $i < count($tempTimes); $i++) {
            if(strtotime($timeStart) >= strtotime($tempTimes[$i][0]) && strtotime($timeStart) <= strtotime($tempTimes[$i][1])) {
                $timeCheck = false;
                break;
            } else {
                $timeCheck = true;
            }
        }

        $workoutTime = $timeStart."-".$timeEnd;

        if($timeCheck) {
            $config['allowed_types'] = 'jpg|png';
            $config['upload_path'] = './uploads/';
            $config['encrypt_name'] = true;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload('workout_image')) {
                $workout_image = $this->upload->data('file_name');
                $service = array(
                    'services_title' => $this->input->post('workout_title'),
                    'services_price' => $this->input->post('workout_price'),
                    'services_description' => $this->input->post('workout_description'),
                    'services_type' => $this->input->post('workout_type'),
                    'services_availability' => $workout_availability_temp,
                    'services_time' => $workoutTime,
                    'services_day' => implode(' ', $arrDay),
                    'services_session' => $this->input->post('workout_session'),
                    'services_duration' => $this->input->post('workout_duration'),
                    'services_image' => $workout_image,
                    'users_name' => $this->session->userdata('username'),
                    'users_id' => $userid
                );
            }

            else{
                $service = array(
                    'services_title' => $this->input->post('workout_title'),
                    'services_price' => $this->input->post('workout_price'),
                    'services_description' => $this->input->post('workout_description'),
                    'services_type' => $this->input->post('workout_type'),
                    'services_availability' => $workout_availability_temp,
                    'services_time' => $workoutTime,
                    'services_day' => implode(' ', $arrDay),
                    'services_session' => $this->input->post('workout_session'),
                    'services_duration' => $this->input->post('workout_duration'),
                    'users_name' => $this->session->userdata('username'),
                    'users_id' => $userid
                );
            }
    
            $this->user_model->insert_service($service);
            redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
        } else {
            $this->session->set_flashdata('valid', 'There is a conflict in your schedule!');
            redirect(base_url() . 'user/profile/' . $this->session->userdata('userusername'));
        }

        //echo $workoutTime;
    }

    public function service()
    {
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        $data["ratings"] = $this->user_model->get_rating_by_id($serviceid);
        $data["coach"] = $this->user_model->get_coach_by_service($serviceid);
        if($this->uri->segment(4)) {
            $data["rated"] = $this->user_model->if_rated($this->uri->segment(4), $this->session->userdata('userusername'));
        }
        //print_r($data["rated"]);
        $this->navbar();
        $this->load->view("service_details", $data);
        $this->footer();
    }

    public function submit_review()
    {
        $rating = array(
            'services_id' => $this->uri->segment(3),
            'users_id' => $this->session->userdata('userid'),
            'users_username' => $this->session->userdata('userusername'),
            'ratings_rate' => $this->input->post('rating'),
            'ratings_comment' => $this->input->post('review_comment')
        );
        $this->user_model->insert_rating($rating);
        $this->user_model->update_rate($this->uri->segment(4));
        redirect(base_url() . 'user/service/' . $rating['services_id']);
    }

    public function messages()
    {
        $this->load->view("messages");
    }

    public function topup()
    {
        $this->navbar();
        $this->load->view("topup");
    }

    public function email_confirm_booking()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        $temp = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp);
        $this->load->view("email_confirm_booking", $data);
    }

    public function email_confirm_cashout()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        $temp = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp);
        $this->load->view("email_confirm_cashout", $data);
    }

    public function email_complete_workout()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        $temp = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp);
        $this->load->view("email_complete_workout", $data);
    }

    public function success_order()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
            $useremail = $row->users_email;
        }
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        foreach ($data["services"] as $row) {
            $serviceprice = $row->services_price;
        }
        $temp = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp);
        $orderid = $data["orders"]->orders_id;
        $temp2 = $this->user_model->fetch_all_orders_by_id($orderid);
        $username2 = $temp2[0]->orders_to;
        $data["users2"] = $this->user_model->fetch_data($username2);
        foreach ($data["users2"] as $row) {
            $userid2 = $row->users_id;
            $useremail2 = $row->users_email;
        }
        date_default_timezone_set('Asia/Manila');
        $time = date("g:ia");
        $msgTrainee = "You successfully bought a service! Please check your bookings.";
        $msgCoach = "A trainee has bought your service! Please check your pending list.";

        $this->user_model->insert_notif($userid, $time, $msgTrainee);
        $this->user_model->insert_notif($userid2, $time, $msgCoach);

        $message = $this->load->view('email_confirm_booking', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to(array($useremail, $useremail2));
        $this->email->subject('Booking Receipt');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        $this->navbar();
        $this->load->view("success_order", $data);
    }

    public function success()
    {
        $data['value'] = $_COOKIE['value'];
        $temp = $this->user_model->get_wallet($this->session->userdata('userid'));
        $newVal = floatval($data['value']) + floatval($temp[0]->users_wallet);
        $this->user_model->success_topup(floatval($newVal));
        $this->user_model->insert_topup($this->session->userdata('userid'), floatval($data['value']));

        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $useremail = $row->users_email;
        }
        
        $temp = $this->user_model->fetch_all_payments();
        $data["payments"] = end($temp);

        $message = $this->load->view('email_topup_success', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Topup Success');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }


        unset($_COOKIE['value']);
        redirect(base_url() . 'user/topup');
    }

    public function aboutus()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $this->navbar();
        $this->load->view("aboutus", $data);
        $this->footer();
    }

    public function nutrition()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $this->navbar();
        $this->load->view("nutrition", $data);
        $this->footer();
    }

    public function podcast()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $this->navbar();
        $this->load->view("podcast", $data);
        $this->footer();
    }

    public function faq()
    {
        $this->navbar();
        $this->load->view("faq");
        $this->footer();
    }

    public function deactivate_services()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $this->user_model->deactivate_services($id);

            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function activate_services()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $this->user_model->activate_services($id);

            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function checkout()
    {
        $username = $this->session->userdata('userusername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $serviceid = $this->uri->segment(3);
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        $data["coach"] = $this->user_model->get_coach_by_service($serviceid);
        $this->navbar();
        $this->load->view("checkout_service", $data);
    }

    public function avail_service()
    {
        $from = $this->session->userdata('userusername');
        $temp = $this->user_model->get_coach_by_service($this->uri->segment(3));
        $to = $temp[0]->users_username;
        $amount = floatval($temp[0]->services_price);
        $serviceid = $this->uri->segment(3);
        $duration = $temp[0]->services_duration;
        date_default_timezone_set('Asia/Manila');
        $datetime = date('Y/m/d H:i:s');
        $this->user_model->insert_order($from, $to, $amount, $serviceid, $duration, $datetime);
        redirect(base_url() . 'user/success_order/' . $serviceid);
    }

    public function confirm()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $sale_id = $this->user_model->get_servicebyorder($id);
            $sale2 = $this->user_model->get_servicesale($sale_id[0]->services_id);
            $temp = intval($sale2[0]->services_sale) + 1;
            $this->user_model->update_servicesale($sale_id[0]->services_id, $temp);
            $this->user_model->confirm_trainee($id);
            $temp2 = $this->user_model->fetch_all_orders_by_id($id);
            $amount = floatval($temp2[0]->orders_amount);
            $wallet = $this->user_model->get_wallet_by_username($temp2[0]->orders_from);
            $new_wallet = intval($wallet[0]->users_wallet) - intval($amount);
            $wallet_coach = $this->user_model->get_wallet_by_username($temp2[0]->orders_to);
            $new_wallet_coach = intval($wallet_coach[0]->users_wallet) + intval($amount);
            $this->user_model->update_trainee_wallet($new_wallet, $temp2[0]->orders_from);
            $this->user_model->update_coach_wallet($new_wallet_coach, $temp2[0]->orders_to);

            $username = $temp2[0]->orders_from;
            $data["users"] = $this->user_model->fetch_data($username);
            foreach ($data["users"] as $row) {
                $userid = $row->users_id;
                $useremail = $row->users_email;
            }
            $serviceid = $this->uri->segment(3);
            $data["services"] = $this->user_model->get_service_by_id($serviceid);
            foreach ($data["services"] as $row) {
                $serviceprice = $row->services_price;
            }
            $temp = $this->user_model->fetch_all_orders();
            $data["orders"] = end($temp);
            $orderid = $data["orders"]->orders_id;

            date_default_timezone_set('Asia/Manila');
            $time = date("g:ia");
            $msgTrainee = "Your order BFTWRKOUT00".$id." has been confirmed! Please check your bookings.";

            $this->user_model->insert_notif($userid, $time, $msgTrainee);

            $message = $this->load->view('email_booking_accepted', $data, true);
            $this->load->config('email');
            $this->load->library('email');
            $this->email->set_newline("\r\n");
            $this->email->from($this->config->item('smtp_user'));
            $this->email->to($useremail);
            $this->email->subject('Booking Accepted');
            $this->email->message($message);

            if ($this->email->send()) {
                $this->session->set_flashdata('msg', 'Nice one');
            } else {
                $this->session->set_flashdata('msg', $this->email->print_debugger());
            }
            redirect($_SERVER['HTTP_REFERER']);
        }

        //$this->user_model->update_services($id);
    }

    public function decline()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $temp = $this->user_model->fetch_all_orders_by_id($id);
            $temp2 = $this->user_model->fetch_data($temp[0]->orders_from);
            $useremail = $temp2[0]->users_email;
            $userid = $temp2[0]->users_id;

            date_default_timezone_set('Asia/Manila');
            $time = date("g:ia");
            $msgTrainee = "Your order BFTWRKOUT00".$id." has been declined!";

            $this->user_model->insert_notif($userid, $time, $msgTrainee);

            $message = "
                        <html>
                            <head>
                                <title>Order Declined</title>
                            </head>
                            <body>
                                <h2>Order Declined.</h2>
                                <p>Your order BFTWRKOUT00'.$id.' has been declined by the coach due to maximum capacity of trainees in the said workout. </p>.
                            </body>
                        </html>
            ";
            $this->load->config('email');
            $this->load->library('email');
            $this->email->set_newline("\r\n");
            $this->email->from($this->config->item('smtp_user'));
            $this->email->to($useremail);
            $this->email->subject('Order Number '.'BFTWRKOUT00'.$id.' has been declined');
            $this->email->message($message);

            if ($this->email->send()) {
                $this->session->set_flashdata('msg', '');
            } else {
                $this->session->set_flashdata('msg', $this->email->print_debugger());
            }
            $this->user_model->delete_orders_by_id($id);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function add_session()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $temp = $this->user_model->fetch_all_orders_by_id($id);
            $session = intval($temp[0]->orders_duration);
            $new_session = $session + 1;
            $this->user_model->update_orders_duration($new_session, $id);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function minus_session()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $temp = $this->user_model->fetch_all_orders_by_id($id);
            $session = intval($temp[0]->orders_duration);
            $new_session = $session - 1;
            $this->user_model->update_orders_duration($new_session, $id);
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    public function complete_orders()
    {
        if (isset($_GET['id'])) {
            $id = $_GET['id'];
            $temp = $this->user_model->fetch_all_orders_by_id($id);
            $session = intval($temp[0]->orders_remarks);
            $new_session = $session + 1;
            $this->user_model->update_orders_remarks($new_session, $id);
            $username = $temp[0]->orders_from;
            $data["users"] = $this->user_model->fetch_data($username);
            foreach ($data["users"] as $row) {
                $userid = $row->users_id;
                $useremail = $row->users_email;
            }
            $temp = $this->user_model->fetch_all_orders();//fg
            $data["orders"] = end($temp);

            date_default_timezone_set('Asia/Manila');
            $time = date("g:ia");
            $msgTrainee = "Your order BFTWRKOUT00".$id." has been completed!";

            $this->user_model->insert_notif($userid, $time, $msgTrainee);

            $message = $this->load->view('email_complete_workout', $data, true);
            $this->load->config('email');
            $this->load->library('email');
            $this->email->set_newline("\r\n");
            $this->email->from($this->config->item('smtp_user'));
            $this->email->to($useremail);
            $this->email->subject('Order Number '.'BFTWRKOUT00'.$id.' has been completed');
            $this->email->message($message);

            if ($this->email->send()) {
                $this->session->set_flashdata('msg', '');
            } else {
                $this->session->set_flashdata('msg', $this->email->print_debugger());
            }
            redirect($_SERVER['HTTP_REFERER']);
        }
    }

    /*public function registercoach_mobile()
    {
        $result = '';
        $user = array(
            'users_account' => $this->input->post('account'),
            'users_avatar' => $this->input->post('shuffledfilename'),
            'users_name' => $this->input->post('name'),
            'users_username' => $this->input->post('username'),
            'users_birthdate' => $this->input->post('birthdate'),
            'users_email' => $this->input->post('email'),
            'users_password' => $this->input->post('password'),
            'users_code' => $this->input->post('code'),
            'users_active' => false,
            'users_wallet' => 0
        );
        $id = $this->user_model->insert($user);

        $detail = array(
            'Age' => $this->input->post('age'),
            'Height' => floatval($this->input->post('theight')),
            'ID' => $id,
            'BMI' => floatval($this->input->post('tbmi'))
        );

        $this->user_model->coach($detail);

        $base = $_POST["encoded"];
        $filename = $_POST["shuffledfilename"];
        $binary = base64_decode($base);
        header('Content-Type: bitmap; charset=utf-8');
        $file = fopen('./uploads/' . $filename, 'wb');
        fwrite($file, $binary);
        fclose($file);
        $result = "true";
        echo $id;
    }*/

    public function register_mobile()
    {
        $result = '';

        $tz  = new DateTimeZone('Asia/Manila');
        $age = DateTime::createFromFormat('Y-m-d', $this->input->post('birthdate'), $tz)
                ->diff(new DateTime('now', $tz))
                ->y;
            
        if($age < 18) {
            $id = 0;
            $result = "false";
            echo $id.':'.$result;
        } else {
            $user = array(
                'users_account' => $this->input->post('account'),
                'users_avatar' => $this->input->post('shuffledfilename'),
                'users_name' => $this->input->post('name'),
                'users_username' => $this->input->post('username'),
                'users_birthdate' => $this->input->post('birthdate'),
                'users_email' => $this->input->post('email'),
                'users_password' => $this->input->post('password'),
                'users_code' => $this->input->post('code'),
                'users_active' => false,
                'users_wallet' => 0
            );
            $id = $this->user_model->insert($user);
    
            $detail = array(
                'Age' => $age,
                'requirement' => $this->input->post('shuffledId'),
                'ID' => $id
            );
            $this->user_model->coach($detail);
    
            $base = $_POST["encoded"];
            $filename = $_POST["shuffledfilename"];
            $binary = base64_decode($base);
            header('Content-Type: bitmap; charset=utf-8');
            $file = fopen('./uploads/' . $filename, 'wb');
            fwrite($file, $binary);
            fclose($file);
            $baseId = $_POST["encodedId"];
            $filenameId = $_POST["shuffledId"];
            $binaryId = base64_decode($baseId);
            header('Content-Type: bitmap; charset=utf-8');
            $fileId = fopen('./uploads/'.$filenameId, 'wb');
            fwrite($fileId, $binaryId);
            fclose($fileId);
            $result = "true";
            echo $id.':'.$result;
        }
    }

    public function registertrainee_mobile()
    {
        $result = '';
        $tz  = new DateTimeZone('Asia/Manila');
        $age = DateTime::createFromFormat('Y-m-d', $this->input->post('tbirthdate'), $tz)
                ->diff(new DateTime('now', $tz))
                ->y;
            
        if($age < 18) {
            $id = 0;
            $result = "false";
            echo $id.':'.$result;
        } else {
            $user = array(
                'users_account' => $this->input->post('taccount'),
                'users_avatar' => $this->input->post('tshuffledfilename'),
                'users_name' => $this->input->post('tname'),
                'users_username' => $this->input->post('tusername'),
                'users_birthdate' => $this->input->post('tbirthdate'),
                'users_email' => $this->input->post('temail'),
                'users_password' => $this->input->post('tpassword'),
                'users_code' => $this->input->post('tcode'),
                'users_active' => false,
                'users_wallet' => 0
            );
            $id = $this->user_model->insert($user);
    
            $tdetail = array(
                'Age' => $age,
                'Height' => floatval($this->input->post('theight')),
                'Weight' => floatval($this->input->post('tweight')),
                'Health' => $this->input->post('thealth'),
                'ID' => $id,
                'BMI' => floatval($this->input->post('tbmi'))
            );
    
            $this->user_model->trainee($tdetail);
    
            $base = $_POST["tencoded"];
            $filename = $_POST["tshuffledfilename"];
            $binary = base64_decode($base);
            header('Content-Type: bitmap; charset=utf-8');
            $file = fopen('./uploads/' . $filename, 'wb');
            fwrite($file, $binary);
            fclose($file);
            $result = "true";
            echo $id.':'.$result;
        }

    }

    public function login_mobile()
    {
        $result = '';
        $name = '';
        $id = '';
        $acc = '';
        $username = $this->input->post('username');
        $password = $this->input->post('password');
        $data["users"] = $this->user_model->fetch_data($username);
        if (!empty($data["users"])) {
            foreach ($data["users"] as $row) {
                if ($password != $row->users_password) {
                    $result = "false";
                } else {
                    if ($row->users_active == 0) {
                        $result = "notactive";
                    } else {
                        $result = "true";
                        $name = $row->users_name;
                        $id = $row->users_id;
                        $acc = $row->users_account;
                    }
                }
            }
        } else {
            $result = "false";
        }
        
        echo $result.':'.$name.':'.$id.':'.$acc;
    }

    public function createWorkout_mobile()
    {
        $result = '';
        $workout_availability_temp = 1;
        $data["users"] = $this->user_model->fetch_data($this->input->post('username'));
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
        }
        $service = array(
            'services_title' => $this->input->post('workout_title'),
            'services_price' => $this->input->post('workout_price'),
            'services_description' => $this->input->post('workout_description'),
            'services_type' => $this->input->post('workout_type'),
            'services_availability' => $workout_availability_temp,
            'services_time' => $this->input->post('workout_time'),
            'services_day' => $this->input->post('workout_day'),
            'services_session' => $this->input->post('workout_session'),
            'services_duration' => $this->input->post('workout_duration'),
            'users_name' => $this->input->post('name'),
            'users_id' => $userid
        );
        $this->user_model->insert_service($service);
    }

    public function fetchdata_mobile()
    {
        $result = '';
        $account = '';
        $image = '';
        $wallet = '';
        $email = '';
        $username = $this->input->post('dataUsername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $account = $row->users_account;
            $image = $row->users_avatar;
            $wallet = $row->users_wallet;
            $email = $row->users_email;
        }
        $result = "true";
        echo $result . ':' . $account . ':' . $image . ':' . $wallet . ':' . $email;
    }

    public function fetchservices_mobile()
    {
        $result = '';
        $title = '';
        $description = '';
        $price = '';
        $coach = '';
        $workout = '';
        $time = '';
        $day = '';
        $duration = '';
        $userid = '';
        $serviceid = $this->input->post('dataService');
        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        foreach ($data["services"] as $row) {
            $title = $row->services_title;
            $description = $row->services_description;
            $price = $row->services_price;
            $coach = $row->users_name;
            $workout = $row->services_type;
            $time = $row->services_time;
            $day = $row->services_day;
            $duration = $row->services_duration;
            $userid = $row->users_id;
        }
        $result = "true";
        echo $result . '<>' . $title . '<>' . $description . '<>' . $price . '<>' . $coach . '<>' . $workout . '<>' . $time . '<>' . $day . '<>' . $duration . '<>' . $userid;
    }

    public function topup_mobile()
    {
        $result = '';
        $amount = $this->input->post('amount');
        $data['value'] = $amount;
        $temp = $this->user_model->get_wallet_by_username($this->input->post('dataUsername'));
        $newVal = floatval($amount) + floatval($temp[0]->users_wallet);
        $this->user_model->success_topup_mobile(
            floatval($newVal),
            $this->input->post('dataUsername')
        );
        $this->user_model->insert_topup($temp[0]->users_id, floatval($amount));

        $username = $this->input->post('dataUsername');
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $useremail = $row->users_email;
        }

        $temp = $this->user_model->fetch_all_payments();
        $data["payments"] = end($temp);

        $message = $this->load->view('email_topup_success', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Topup Success');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }


        $result = "true";
        echo $result;
    }

    public function submitreview_mobile() {
        $result = '';
        $serviceid = $this->input->post('serviceid');
        $username = $this->input->post('username');
        $rating = $this->input->post('rating');
        $comment = $this->input->post('comment');

        $data["users"] = $this->user_model->fetch_data($username);
        foreach($data["users"] as $row) {
            $userid = $row->users_id;
        }

        $ratingArr = array(
            'services_id'=>$serviceid,
            'users_id'=>$userid,
            'users_username'=>$username,
            'ratings_rate'=>$rating,
            'ratings_comment'=>$comment
        );
        $this->user_model->insert_rating($ratingArr);
        $this->user_model->update_rate($this->input->post('orderid'));
        $result = "true";
        echo $result;
    }

    public function deactivateservice_mobile() {
        $result = '';
        $id = $this->input->post('serviceid');
        $this->user_model->deactivate_services($id);
        $result = "true";
        echo $result;
    }

    public function activateservice_mobile() {
        $result = '';
        $id = $this->input->post('serviceid');
        $this->user_model->activate_services($id);
        $result = "true";
        echo $result;
    }

    public function confirmtrainee_mobile() {
        $result = '';
        $id = $this->input->post('orderid');
        $sale_id = $this->user_model->get_servicebyorder($id);
        $sale2 = $this->user_model->get_servicesale($sale_id[0]->services_id);
        $temp = intval($sale2[0]->services_sale) + 1;
        $this->user_model->update_servicesale($sale_id[0]->services_id, $temp);
        $this->user_model->confirm_trainee($id);
        $temp2 = $this->user_model->fetch_all_orders_by_id($id);
        $amount = floatval($temp2[0]->orders_amount);
        $wallet = $this->user_model->get_wallet_by_username($temp2[0]->orders_from);
        $new_wallet = intval($wallet[0]->users_wallet) - intval($amount);
        $wallet_coach = $this->user_model->get_wallet_by_username($temp2[0]->orders_to);
        $new_wallet_coach = intval($wallet_coach[0]->users_wallet) + intval($amount);
        $this->user_model->update_trainee_wallet($new_wallet, $temp2[0]->orders_from);
        $this->user_model->update_coach_wallet($new_wallet_coach, $temp2[0]->orders_to);

        $username = $temp2[0]->orders_from;
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
            $useremail = $row->users_email;
        }

        $temp3 = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp3);
        $orderid = $data["orders"]->orders_id;

        date_default_timezone_set('Asia/Manila');
        $time = date("g:ia");
        $msgTrainee = "Your order BFTWRKOUT00".$id." has been confirmed! Please check your bookings.";

        $this->user_model->insert_notif($userid, $time, $msgTrainee);

        $message = $this->load->view('email_booking_accepted', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Booking Accepted');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        $result = "true";
        echo $result;
    }

    public function declinetrainee_mobile() {
        $result = '';
        $id = $this->input->post('orderid');
        $temp = $this->user_model->fetch_all_orders_by_id($id);
        $temp2 = $this->user_model->fetch_data($temp[0]->orders_from);
        $useremail = $temp2[0]->users_email;
        $userid = $temp2[0]->users_id;

        date_default_timezone_set('Asia/Manila');
        $time = date("g:ia");
        $msgTrainee = "Your order BFTWRKOUT00".$id." has been declined!";

        $this->user_model->insert_notif($userid, $time, $msgTrainee);

        $message = "
                    <html>
                        <head>
                            <title>Order Declined</title>
                        </head>
                        <body>
                            <h2>Order Declined.</h2>
                            <p>Your order BFTWRKOUT00'.$id.' has been declined by the coach due to maximum capacity of trainees in the said workout. </p>.
                        </body>
                    </html>
        ";
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Order Number '.'BFTWRKOUT00'.$id.' has been declined');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', '');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }
        $this->user_model->delete_orders_by_id($id);
        $result = "true";
        echo $result;
    }

    public function fetchprofile_mobile() {
        $result = '';
        $name = '';
        $account = '';
        $age = '';
        $height = '';
        $weight = '';
        $bmi = '';
        $health = '';
        $image = '';
        $username = $this->input->post('dataUsername');

        $data["users"] = $this->user_model->fetch_data($username);
        foreach($data["users"] as $row) {
            $userid = $row->users_id;
            $name = $row->users_name;
            $account = $row->users_account;
            $image = $row->users_avatar;
        }

        $data["details"] = $this->user_model->get_traineedetails($userid);
        foreach($data["details"] as $row1) {
            $age = $row1->Age;
            $height = $row1->Height;
            $weight = $row1->Weight;
            $bmi = $row1->BMI;
            $health = $row1->Health;
        }

        $result="true";
        echo $result.'<>'.$name.'<>'.$account.'<>'.$age.'<>'.$height.'<>'.$weight.'<>'.$bmi.'<>'.$health.'<>'.$image;
    }

    public function availservice_mobile() {
        $result='';
        $from = $this->input->post('dataUsername');
        $data["users"] = $this->user_model->fetch_data($from);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
            $useremail = $row->users_email;
        }
        $temp = $this->user_model->get_coach_by_service($this->input->post('service'));
        $to = $temp[0]->users_username;
        $userid2 = $temp[0]->users_id;
        $amount = floatval($temp[0]->services_price);
        $duration = $temp[0]->services_duration;
        $serviceid = $this->input->post('service');

        $data["services"] = $this->user_model->get_service_by_id($serviceid);
        foreach ($data["services"] as $row) {
            $serviceprice = $row->services_price;
        }

        $temp = $this->user_model->fetch_all_orders();
        $data["orders"] = end($temp);

        date_default_timezone_set('Asia/Manila');
        $date = date('Y-m-d H:i:s');

        $wallet = $this->user_model->get_wallet($this->input->post('dataUserid'));
        if(intval($wallet[0]->users_wallet) < intval($amount)) {
            $result = "false";
        } else {
            $this->user_model->insert_order($from, $to, $amount, $serviceid, $duration, $date);
            $result = "true";
        }

        date_default_timezone_set('Asia/Manila');
        $time = date("g:ia");
        $msgTrainee = "You successfully bought a service! Please check your bookings.";
        $msgCoach = "A trainee has bought your service! Please check your pending list.";

        $this->user_model->insert_notif($userid, $time, $msgTrainee);
        $this->user_model->insert_notif($userid2, $time, $msgCoach);

        $message = $this->load->view('email_confirm_booking', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Booking Receipt');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        echo $result;
    }

    public function complete_mobile() {
        $result = '';
        $id = $this->input->post('orderid');
        $temp = $this->user_model->fetch_all_orders_by_id($id);
        $session = intval($temp[0]->orders_remarks);
        $new_session = $session + 1;
        $this->user_model->update_orders_remarks($new_session, $id);

        $username = $temp[0]->orders_from;
        $data["users"] = $this->user_model->fetch_data($username);
        foreach ($data["users"] as $row) {
            $userid = $row->users_id;
            $useremail = $row->users_email;
        }

        $temp = $this->user_model->fetch_all_orders();//fg
        $data["orders"] = end($temp);

        date_default_timezone_set('Asia/Manila');
        $time = date("g:ia");
        $msgTrainee = "Your order BFTWRKOUT00".$id." has been completed!";

        $this->user_model->insert_notif($userid, $time, $msgTrainee);

        $message = $this->load->view('email_complete_workout', $data, true);
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($useremail);
        $this->email->subject('Order Number '.'BFTWRKOUT00'.$id.' has been completed');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', '');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        $result = "true";
        echo $result;
    }

    public function decrease_mobile() {
        $result = '';
        $id = $this->input->post('orderid');
        $temp = $this->user_model->fetch_all_orders_by_id($id);
        $session = intval($temp[0]->orders_duration);
        $new_session = $session - 1;
        $this->user_model->update_orders_duration($new_session, $id);
        $result = "true";
        echo $result;
    }

    public function increase_mobile() {
        $result = '';
        $id = $this->input->post('orderid');
        $temp = $this->user_model->fetch_all_orders_by_id($id);
        $session = intval($temp[0]->orders_duration);
        $new_session = $session + 1;
        $this->user_model->update_orders_duration($new_session, $id);
        $result = "true";
        echo $result;
    }

    public function gethealth_mobile() {
        $result = '';
        $health = '';
        $id = $this->input->post('dataUserid');
        $temp = $this->user_model->get_health($id);
        $health = $temp[0]->Health;
        $result = "true";
        echo $result.':'.$health;
    }

    public function cashout_mobile() {
        $result = '';
        $data["users"] = $this->user_model->fetch_user($this->input->post('userid'));
        foreach ($data["users"] as $row) {
            $wallet = $row->users_wallet;
            $email = $row->users_email;
            $username = $row->users_username;
        }

        date_default_timezone_set('Asia/Manila');
        $datetime = date('Y/m/d H:i:s');
        $cashout = array(
            'cashout_from' => $username,
            'cashout_amount' => $wallet,
            'cashout_phone' => $this->input->post("mobilenum"),
            'cashout_email' => $email,
            'cashout_datetime' => $datetime,
            'users_id' => $this->input->post('userid')
        );
        $this->user_model->insert_cashout($cashout);

        $temp = $this->user_model->get_cashout($this->input->post('userid'));
        $data["id"] = end($temp);
        $cashoutid = $data["id"]->cashout_id;

        $message = "
                        <html>
                            <head>
                                <title>Cashout Request</title>
                            </head>
                            <body>
                                <h2>You have requested a cashout.</h2>
                                <p>Cashout Details:</p>
                                <p>Email: " . $email . "</p>
                                <p>Amount: " . $wallet . "</p>
                                <p>Transaction ID: BFTCSHOT00" . $cashoutid . "</p>
                            </body>
                        </html>
        ";
        $this->load->config('email');
        $this->load->library('email');
        $this->email->set_newline("\r\n");
        $this->email->from($this->config->item('smtp_user'));
        $this->email->to($email);
        $this->email->subject('Cashout Request');
        $this->email->message($message);

        if ($this->email->send()) {
            $this->session->set_flashdata('msg', 'Nice one');
        } else {
            $this->session->set_flashdata('msg', $this->email->print_debugger());
        }

        $result = "true";
        echo $result;
    }

    public function view_profile(){
 


        if (!$this->session->userdata('userusername')) {
            redirect(base_url());
        }

        $this->navbar();
        $profile_username = $this->uri->segment(3);
        $users_name = $this->user_model->fetch_user_by_username($profile_username);

        //fetch nang coach profile
        $data["coachdata"] = $this->user_model->get_user_by_username($profile_username);

        //$userid = ($users_name[0]->users_id);

        //fetch nang service
        $users_name = ($users_name[0]->users_name);
        $data["coachservices"] = $this->user_model->fetch_service_by_name($users_name);
        $this->load->view('view_profile',$data);
        $this->footer();

    }
}
