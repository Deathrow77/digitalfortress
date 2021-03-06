<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Socialite;
use App\users;
use App\leaderboard;
use App\hints;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Hash;
use DateTime;
use Mail;


class HomeController extends Controller
{
    private function leaderboard_entry($user)
    {
        $lentry = new leaderboard();
        $lentry->email = $user->email;
        $lentry->username = $user->username;
        $lentry->round_id = intval(1);
        $lentry->save();
    }
    private function sortcomp($a,$b){
        if ($a['round_id']>$b['round_id']){
                return -1;
        }
        else if($a['round_id']==$b['round_id']){

            if($a['updated_at']>$b['updated_at'])
                return 1;
            else 
                return -1;
        }
        else{
            return 1;
        }
    }
    
    public function hintview()
    {
        $hints=hints::all()->sortByDesc('id')->toArray();
        $no=count($hints);
        if($no==0)
            return view('hints')->with(['nohints'=>1]);
        return view('hints')->with(['tab'=>6,'hints'=>$hints]);
    }

    public function landing(){
        return view('landing');
    }

    public function rules(){
        return view('quiz/rules')->with(['tab'=>3,'dashname'=>'Rules and Regulations']);
    }


    public function dashboard()
    {

        if (session()->has('email'))
        {
            $email = session('email');
            $lboard = leaderboard::all()->toArray();
            $people = count($lboard);

            usort($lboard, array($this,"sortcomp"));

            $key = array_search($email, array_column($lboard, 'email'));
            
            $lboard = array_slice($lboard, ($key-4>0)?$key-4:0, 9, true);

            $cc = leaderboard::where('round_id','>',$lboard[$key]['round_id'])->count();

            return view('dashboard')->with(['name'=>session('name'),'tab'=>1,'stats'=>$lboard,'people'=>$people,'dashname'=>'My Dashboard', 'key'=>$key, 'cc'=>$cc]);
        }
        else{
            return view('loginregister')->with(['tab'=>1,'dashname'=>'Login Portal']);
        }
    }

    public function lboard()
    {
        $lboard = leaderboard::all()->toArray();
        $sz = count($lboard);
        usort($lboard, array($this,"sortcomp"));
        return view('quiz/leaderboard')->with(['tab'=>4,'sz'=>$sz,'stand'=>$lboard,'name'=>session('name'),'dashname'=>'Leaderboard']);
    }

    public function login(Request $requests)
    {
        $email = $requests->input('email');
        $password = $requests->input('password');
        $profile = users::where('email',$email)->first();
        if(!empty($profile) && Hash::check($password,$profile->password))
        {
            if($profile->verified==0)
                return view('quiz/verifyemail')->with(['newusertext'=>'error','newusertitle'=>'Already Registered','newusermessage'=>'You still have not verified your email address!']);

            session()->put(['name'=>$profile['username'],'email'=>$profile['email']]);
            return redirect('dashboard');
        }
        return view('dashboard')->with(['newusertext'=>'error','newusertitle'=>'Invalid Credentials','newusermessage'=>'Wrong Username or Password!']);
    }
    public function verifyemail($token)
    {

        if($token=="reload" && session()->has('temp_email'))
            return view('quiz/verifyemail');
        else if($token=="reload")
            return redirect('dashboard');

        $profile=users::where('token',$token)->first();
        if(!empty($profile))
        {
             $profile->verified=1;
             $profile->token='NULL';
             $profile->save();

             $this->leaderboard_entry($profile);
             session()->flush();
             $message='You have succesfully verified your email. Please Login Now. Break A Leg!';
             return view('dashboard')->with(['newusertitle'=>'Thanks for Registering!!','newusertext'=>'success','newusermessage'=>$message,'tab'=>1]);
        }
        return redirect('dashboard');
    }
    public function resendmail()
    {
        if(session()->has('temp_email'))
        {
            $mid=session('temp_email');
            $token=session('temp_token');
            $siteurl = env('APP_URL','http://localhost:8000');
            Mail::send('quiz.email',["token"=>$token,"siteurl"=>$siteurl],function($message) use($mid)
            {
                 $message->from('noreply@gmail.com','Digital Fortress');
                 $message->to($mid);
                 $message->subject("Welcome to Digital Fortress!");
            });
            $message = 'Verification mail has been resent! Kindly check your inbox';
            return view('quiz/verifyemail')->with(['newusertext'=>'success','newusertitle'=>'Email Resent','newusermessage'=>$message]);
        }
        return redirect('dashboard');
    }
    public function register(Request $requests)
    {
        $rules = array(
        'username'    => 'required', 
        'email'       => 'required',
        'password'    => 'required|min:3'
        );

        $this->validate($requests, $rules);
        
        $profile = users::where('email',$requests->input('email'))->first();

        if(!empty($profile))
        {
            if($profile->verified==1)
                return view('dashboard')->with(['newusertext'=>'error','newusertitle'=>'Email Taken','newusermessage'=>'This email has already been registered and verified,','tab'=>1]);
            else
                return view('dashboard')->with(['newusertext'=>'info','newusertitle'=>'Already Registered','newusermessage'=>'You have registered but not verified your email address! Kindly Check your Inbox','tab'=>1]);
        }
     //   return $requests->input('email');
        $newuser = new users;
        $newuser->email = $requests->input('email');
        $newuser->username = $requests->input('username');
        $newuser->password = Hash::make($requests->input('password'));
        $newuser->verified=0;
        $timestamp=new DateTime();
        $timestamp = $timestamp->getTimestamp();
        $newuser->token=md5($timestamp);
        $siteurl = env('APP_URL','http://localhost:8000');
        Mail::send('quiz.email',["token"=>$newuser->token,"siteurl"=>$siteurl],function($message) use($requests)
        {
             $message->from('noreply@gmail.com','Digital Fortress');
             $message->to($requests->input('email'));
             $message->subject("Welcome to Digital Fortress!");
        });
        
        $newuser->save();
        //$this->leaderboard_entry($newuser);
        session()->put(['temp_email'=>$newuser['email'],'temp_token'=>$newuser['token']]);
        //session()->put(['name'=>$newuser['username'],'email'=>$newuser['email']]);
        $message = 'You have succesfully registered for Digital Fortress. Kindly verify your email address!';
        
        //return view('dashboard')->with(['email'=>$newuser['email'],'name'=>$newuser['username'],'newusertext'=>$message,'tab'=>1]);
        return view('quiz/verifyemail')->with(['newusertext'=>'success','newusertitle'=>'Congratulations!','newusermessage'=>$message]);
        //return redirect('verifyemail');
    }

    public function sociallogin($id)
    {
        return Socialite::driver($id)->redirect();
    }


    public function socialcallback(Request $requests, $id)
    {
       
        $user = Socialite::driver($id)->user();
        $email = $user->getEmail();
        
        $profile = users::where('email',$email)->first();
        $flag=0;
        $password='';

        if(empty($profile))
        {
            $profile = new users;
            $profile->email     = $user->getEmail();
            $profile->username  = $user->getName();
            $password           = str_random(8);
            $profile->password  = Hash::make($password);
            $profile->verified=1;
            $profile->token='NULL';
            $profile->save();

            $this->leaderboard_entry($profile);
            $flag=1;
        }
        session()->put(['email'=>$email,'name'=>$profile['username'] ]);

        if($flag==1){
            $message = "Your password is ".$password.'.\n\nUse this to login directly.\n\nBREAK A LEG !!';
            return view('dashboard')->with(['email'=>$email,'name'=>$profile['username'],'newusermessage'=>$message,'newusertext'=>'success','newusertitle'=>'Congratulations!','tab'=>1]);
        }

        return redirect('dashboard');

    }
    public function logout()
    {
        session()->flush();
        return redirect('dashboard');
    }
    
    public function myprofile()
    {
        $username = users::where('email',session('email'))->select('username')->get()->first()['username'];
        return view('quiz/myprofile')->with(['tab'=>5,'dashname'=>'My Profile','name'=>$username,'email'=>session('email')]);
    }

    public function updatepassword(Request $requests)
    {

        $password = users::where('email',session('email'))->select('password')->get()->first();
        $upassword = $requests->input('cp');
        $npassword = Hash::make($requests->input('np'));
        if(strlen($upassword)<3)
            return 2;
        if (Hash::check($upassword, $password->password)) {
            users::where('email',session('email'))->update(['password'=>$npassword]);
            return 1;
        }
        else
            return 3;
    }
}
