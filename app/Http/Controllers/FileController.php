<?php namespace App\Http\Controllers;

use App\Fileentry;
use App\User;
use App\FileStream;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Pion\Laravel\ChunkUpload\Exceptions\UploadMissingFileException;
use Pion\Laravel\ChunkUpload\Handler\AbstractHandler;
use Pion\Laravel\ChunkUpload\Handler\HandlerFactory;
use Pion\Laravel\ChunkUpload\Receiver\FileReceiver;


class FileController extends Controller {

    /**
     * @param Request $request
     * @return array
     */
    public function chunk(Request $request) {
        // create the file receiver
        $receiver = new FileReceiver("file", $request, HandlerFactory::classFromRequest($request));
        // check if the upload is success, throw exception or return response you need
        if ($receiver->isUploaded() === false) {
            abort(400, 'File not found');
        }
        // receive the file
        $save = $receiver->receive();

        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        array_push($path_array, $save->getFile()->getClientOriginalName());
        $path = implode('/', $path_array);
        
        if ( Auth::user()->size() + $request->input('resumableTotalSize') > Auth::user()->offer()->capacity)
            abort(400, 'Not enough space');

        if (Storage::disk('files')->exists($path))
            abort(400, 'File with this name already exists');

        // check if the upload has finished (in chunk mode it will send smaller files)
        if ($save->isFinished()) {
            // save the file and return any response you need, current example uses `move` function. If you are
            // not using move, you need to manually delete the file by unlink($save->getFile()->getPathname())
            return $this->saveFile($save->getFile(), dirname($path));
        }
        // we are in chunk mode, lets send the current progress
        $handler = $save->handler();
        return [
            "done" => $handler->getPercentageDone(),
            "uuid" => $request->input('resumableIdentifier')
        ];
    }



    /**
     * Saves the file
     *
     * @param UploadedFile $file
     * @param $path
     * @return array
     */
    protected function saveFile(UploadedFile $file, $path)
    {
        $fileName = $this->createFilename($file);
        $fileType = $file->getClientMimeType();
        $fileSize = $file->getClientSize();
        // Build the file path
        $finalPath = Storage::disk('files')->getDriver()->getAdapter()->getPathPrefix() . $path;
        // move the file name
        Storage::disk('files')->makeDirectory( Auth::id(), 0711, true, true);
        $file->move($finalPath, $fileName);

		$entry = new Fileentry();
		$entry->user_id = Auth::id();
		$entry->mime = $fileType;
		$entry->original_filename = $fileName;
		$entry->filename = $fileName;
        $entry->hash = $this->randomId(100);
        $entry->public_hash = '';
        $entry->path =  $path . '/' . $fileName;
        $entry->size =  $fileSize;

		$entry->save();

        return [ 
            "uploaded" => true,
            "size" => Auth::user()->size(),
            "hash" => $entry->hash
        ];
    }

        /**
     * Create unique filename for uploaded file
     * @param UploadedFile $file
     * @return string
     */
    protected function createFilename(UploadedFile $file)
    {
        $extension = $file->getClientOriginalExtension();
        $filename = str_replace(".".$extension, "", $file->getClientOriginalName()); // Filename without extension
        // Add timestamp hash to name of the file
        $filename .= '.' . $extension;
        return $filename;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function upload(Request $request) {
        $this->validate($request, [
            'file' => 'required|file',
            'path' => 'required',
        ]);

        $file = $request->file('file');

        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        array_push($path_array, $file->getClientOriginalName());
        $path = implode('/', $path_array);

        if ( Auth::user()->size() + $file->getSize() > Auth::user()->offer()->capacity)
            abort(400, 'Not enough space');

        if (Storage::disk('files')->exists($path))
            abort(400, 'File with this name already exists');

        $extension = $file->getClientOriginalExtension();
        Storage::disk('files')->makeDirectory( Auth::id(), 0711, true, true);
		Storage::disk('files')->put($path , File::get($file));
		$entry = new Fileentry();
		$entry->user_id = Auth::id();
		$entry->mime = $file->getClientMimeType();
		$entry->original_filename = $file->getClientOriginalName();
		$entry->filename = $file->getClientOriginalName();
        $entry->hash = $this->randomId(100);
        $entry->public_hash = '';
		$entry->path =  $path;
        $entry->size =  $file->getSize();

		$entry->save();

        return [ "size" => Auth::user()->size()];
    }

    /**
     * Generate a public id
     *
     * @return string
     */
    protected function randomId($length){
        $id = str_random($length);
        $validator = Validator::make(['id'=>$id],['id'=>'unique:fileentries,hash']);
        if($validator->fails()){
            $this->randomId();
        }
        return $id;
    }

    /**
     * Send the file directory used for the preview
     *
     * @param $hash
     * @return Response
     */
    public function get($hash){
		$entry = Fileentry::where('hash', $hash)->first();
        if (!$entry) abort(400, 'File not found');

        $filePath = Storage::disk('files')->getDriver()->getAdapter()->getPathPrefix() . $entry->path;
        if (!File::exists($filePath)) abort(400, 'File not found');

        $headers = [
            'Content-Type' => $entry->mime,
            'Content-Disposition' => 'inline; filename="' . $entry->filename .'"'
        ];
        $stream = new FileStream($filePath, $entry->mime);
        return response()->stream(function() use ($stream) {
            $stream->start();
        }, 200, $headers);
	}

    /**
     * Display file details with his public_hash
     *
     * @param $hash
     * @return mixed
     */
    public function show($hash){
        $entry = Fileentry::where('public_hash', $hash)->first();
        if (!$entry) abort(400, 'File not found');
        $entry->creator = $entry->creator();
        $entry->uploaded_date = $entry->created_at->timestamp;
        return $entry;
    }

    /**
     * Display file details with his hash
     *
     * @param $hash
     * @return mixed
     */
    public function detail($hash){
        $entry = Fileentry::where('hash', $hash)->first();
        if (!$entry) abort(400, 'File not found');
        if ($entry->user_id !== Auth::id()) abort(401, 'Unauthorized');
        $entry->creator = $entry->creator();
        $entry->uploaded_date = $entry->created_at->timestamp;
        return $entry;
    }

    /**
     * Set public hash to file if share set true else remove public hash
     *
     * @param $hash
     * @return mixed
     */
    public function share(Request $request) {
        $this->validate($request, [
            'hash' => 'required',
            'status' => 'required',
        ]);
        $entry = Fileentry::where('hash', $request->input('hash'))->first();
        if (!$entry) abort(400, 'File not found');
        if ($entry->user_id !== Auth::id()) abort(401, 'Unauthorized');
        $new_hash = '';
        if ($request->input('status')){
            $new_hash = $this->randomId(6);
            $entry->public_hash = $new_hash;
        }else{
            $entry->public_hash = $new_hash;
        }
        $entry->save();
        return [ 'new_hash' => $new_hash ];
    }

    /**
     * Rename a file
     *
     * @param Request $request
     * @return mixed
     */
    public function rename(Request $request){
        $this->validate($request, [
            'name' => 'required',
            'hash' => 'required',
        ]);

        $entry = Fileentry::where('hash', $request->input('hash'))->first();
        if (!$entry) abort(400, 'File not found');
        if ($entry->user_id !== Auth::id()) abort(401, 'Unauthorized');
        $entry->filename = $request->input('name');
        $file_path_array = explode('/', $entry->path);
        $file_path_array = array_filter($file_path_array, function($value) { return $value !== ''; });
        array_pop($file_path_array);
        array_push($file_path_array, $entry->filename);
        $newPath = implode('/', $file_path_array);

        if (Storage::disk('files')->exists($newPath))
            abort(400, 'File with this name already exists');

        Storage::disk('files')->move($entry->path, $newPath);
        $entry->path = $newPath;
        $entry->save();

        return "success";
    }

    /**
     * Move a file
     *
     * @param Request $request
     * @return mixed
     */
    public function move(Request $request){
        $this->validate($request, [
            'hash' => 'required',
            'newPath' => 'required',
        ]);

        $entry = Fileentry::where('hash', $request->input('hash'))->first();
        if (!$entry) abort(400, 'File not found');
        if ($entry->user_id !== Auth::id()) abort(401, 'Unauthorized');
        $file_path_array = explode('/', $request->input('newPath'));
        $file_path_array = array_filter($file_path_array, function($value) { return $value !== ''; });
        array_unshift($file_path_array, Auth::id());
        array_push($file_path_array, $entry->filename);
        $newPath = implode('/', $file_path_array);

        if (Storage::disk('files')->exists($newPath))
            abort(400, 'File with this name already exists');

        Storage::disk('files')->move($entry->path, $newPath);
        $entry->path = $newPath;
        $entry->save();

        return "success";
    }


    /**
     * Download a file by is hash
     *
     * @param $hash
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($hash){
        $entry = Fileentry::where('hash', $hash)->first();
        if (!$entry) abort(400, 'File not found');
        $entry->downloads+=1;
        $entry->save();
        $path = Storage::disk('files')->getDriver()->getAdapter()->applyPathPrefix($entry->path);

        $headers = array(
            "Content-Disposition: attachment; filename=\"" . basename($path) . "\"",
            "Content-Type: application/force-download",
            "Content-Length: " . filesize($path),
            "Connection: close"
        );
        return response()->download($path, $entry->filename, $headers);
    }

    /**
     * Delete a file with his hash
     *
     * @param $hash
     * @return array
     */
    public function delete($hash){
        $entry = Fileentry::where('hash', $hash)->first();
        if ($entry->user_id != Auth::id()) abort(401, 'Unauthorized');

        Storage::disk('files')->delete($entry->path);
        $entry->delete();

        return [ "size" => Auth::user()->size()];
    }

}