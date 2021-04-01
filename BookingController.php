<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Http\Requests;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{

    /**
     * @var BookingRepository
     */
    protected $repository;
	protected $currentUser;
	protected $request;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
		$this->request = $request;
		$this->getCurrentUser();
        if($user_id = $request->get('user_id')) {
            $response = $this->repository->getUsersJobs($user_id);
        }
        elseif($this->currentUser->user_type == env('ADMIN_ROLE_ID') || $this->currentUser->user_type == env('SUPERADMIN_ROLE_ID'))
        {
            $response = $this->repository->getAll($request);
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);
        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request) {
        $response = $this->repository->store($this->currentUser, $request->all());
        return response($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
		$this->request = $request;
		$this->getCurrentUser();
        $response = $this->repository->updateJob($id, array_except($request->all(), ['_token', 'submit']), $this->currentUser);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $response = $this->repository->storeJobEmail($request->all());
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if($user_id = $request->get('user_id')) {

            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
		$this->request = $request;
		$this->getCurrentUser();
        $response = $this->repository->acceptJob($request->all(), $this->currentUser);

        return response($response);
    }

    public function acceptJobWithId(Request $request)
    {
        $data = $request->get('job_id');
        $response = $this->repository->acceptJobWithId($data, $this->currentUser);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(Request $request)
    {
        $this->request = $request;
		$this->getCurrentUser();
        $response = $this->repository->cancelJobAjax($this->request->all(), $this->currentUser);
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $response = $this->repository->endJob($request->all());
        return response($response);

    }

    public function customerNotCall(Request $request)
    {
        $response = $this->repository->customerNotCall($request->all());
        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
		$this->request = $request;
		$this->getCurrentUser();
        $response = $this->repository->getPotentialJobs($this->currentUser);
        return response($response);
    }

    public function distanceFeed(Request $request)
    {
        $data = $request->all();
		$distance = "";
		$time = "";
		$jobid = "";
		$session = "";
		$flagged = 'no';
		$manually_handled = 'no';
		$by_admin = 'no';
		$admincomment = "";
		
        if ($request->has('distance') && $request->input('distance') != "") {
            $distance = $request->input('distance');
        } 
        if ($request->has('time') && $request->input('time') != "") {
            $time = $request->input('time');
        } 
        if ($request->has('jobid') && $request->input('jobid') != "" ) {
            $jobid = $request->input('jobid');
        }

        if ($request->has('session_time') && $request->input('session_time') != "") {
            $session = $request->input('session_time');
        }
        if ($request->input('flagged') == 'true') {
            if($request->input('admincomment') == '') return "Please, add comment";
            $flagged = 'yes';
        }
        
        if ($request->input('manually_handled') == 'true') {
            $manually_handled = 'yes';
        } 
		
        if ($request->input('by_admin') == 'true') {
            $by_admin = 'yes';
        } 

        if ($request->has('admincomment') && $request->input('admincomment') != "") {
            $admincomment = $request->input('admincomment');
        }
        if ($time || $distance) {
			//This queries should be define in Distance repository class
            $affectedRows = Distance::where('job_id', '=', $jobid)->update(array('distance' => $distance, 'time' => $time));
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            //This queries should be define in Job repository class
			$affectedRows1 = Job::where('id', '=', $jobid)->update(array('admin_comments' => $admincomment, 'flagged' => $flagged, 'session_time' => $session, 'manually_handled' => $manually_handled, 'by_admin' => $by_admin));
        }

        return response('Record updated!');
    }

    public function reopen(Request $request){
        return response($this->repository->reopen($request->all()));
    }

    public function resendNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid'));
        $job_data = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $job_data, '*');
        return response(['success' => 'Push sent']);
    }

    /**
     * Sends SMS to Translator
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $job = $this->repository->find($request->input('jobid');
        $job_data = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

	private function getCurrentUser() { //It should be in base controller or some helper class
		$this->currentUser = $this->request->__authenticatedUser;
	}
}
