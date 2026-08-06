<?php
namespace App\Http\Controllers;
use App\Models\{Course, Enrollment, Certificate, News, Procedure, SopEvaluationAttempt, User};
class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $data = [
            'enrollments' => Enrollment::with('course')->where('user_id',$user->id)->latest()->get(),
            'certificates'=> Certificate::where('user_id',$user->id)->count(),
            'sopPassed'   => SopEvaluationAttempt::where('user_id',$user->id)->where('passed',true)->count(),
            'news'        => News::latest('published_at')->take(3)->get(),
            'admin'       => null,
        ];
        if ($user->isAdmin()) {
            $data['admin'] = [
                'users'      => User::count(),
                'courses'    => Course::count(),
                'procedures' => Procedure::count(),
                'certs'      => Certificate::count(),
            ];
        }
        return view('dashboard', $data);
    }
}
