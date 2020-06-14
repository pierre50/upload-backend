<?php namespace App\Http\Controllers;

use App\Fileentry;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Response;
use ZipArchive;

class DirectoryController extends Controller {

    /**
     * Create a directory
     *
     * @param Request $request
     * @return string
     */
    public function create(Request $request){
        $this->validate($request, [
            'name' => 'required',
            'path' => 'required',
        ]);

        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        array_push($path_array, $request->input('name'));
        $path = implode('/', $path_array);

        if (Storage::disk('files')->exists($path))
            abort(400, 'Directory with this name already exists');

        Storage::disk('files')->makeDirectory($path);

        return 'success';
    }


    /**
     * Display directory details with his path
     *
     * @param Request $request
     * @return mixed
     */
    public function show(Request $request){
        $this->validate($request, [
            'path' => 'required',
        ]);

        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_shift($path_array);
        array_unshift($path_array, Auth::id());
        if (count($path_array) > 1 ){
            $name = end($path_array);
            reset($path_array);
        }else{
            $name = null;
        }
        $path = implode('/', $path_array);
        array_shift($path_array);
        $public_path = implode('/', $path_array);

        $files = Storage::disk('files')->allFiles($path);
        $files = array_filter($files, function($file) { return Fileentry::where('path', $file)->first(); });

        $directories = Storage::disk('files')->allDirectories($path);

        $size = 0;
        foreach($files as $file){
            if ($file){
                $size += Storage::disk('files')->size($file);
            }
        }

        return [
            'name' => $name,
            'size' => $size,
            'count_files' => count($files),
            'count_directories' => count($directories),
            'path' =>  $public_path
        ];
    }

    /**
     * Generate a public id
     *
     * @return string
     */
    public function randomId($name){
        $id = str_random(10) . '_' . $name;
        if (Storage::exists('temp_zips/' . $id)){
            $this->randomId($name);
        }

        return $id;
    }

    /**
     * Save a zip file inside a specific folder and return a id that can be used to download it
     *
     * @param Request $request
     * @return array
     */
    public function saveZip(Request $request){
        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        $path = implode('/', $path_array);

        $files = Storage::disk('files')->allFiles($path);

        if (count($files)==0){
            abort(400, "Cannot zip a empty directory");
        }

        $name = basename($path);
        if (basename($path)==Auth::id()){
            $name = "MyFiles";
        }
        $id = $this->randomId($name);

        // define the name of the archive and create a new ZipArchive instance.
        Storage::makeDirectory( 'temp_zips', 0777, true, true);
        $archiveFile = storage_path("app/temp_zips/" . $id . ".zip");
        $archive = new ZipArchive();
        // check if the archive could be created.
        if ($archive->open($archiveFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            // loop trough all the files and add them to the archive.
            foreach ($files as $file) {
                $path = Storage::disk('files')->getDriver()->getAdapter()->applyPathPrefix($file);
                if ($archive->addFile($path, basename($file))) {
                    // do something here if addFile succeeded, otherwise this statement is unnecessary and can be ignored.
                    continue;
                } else {
                    abort(400, "file `{$file}` could not be added to the zip file: " . $archive->getStatusString());
                }
            }
            // close the archive.
            if ($archive->close()) {
                return [ "temp_id" => explode('_',$id)[0]];
            } else {
                abort(400, "could not close zip file: " . $archive->getStatusString());
            }
        } else {
            abort(400, "zip file could not be created: " . $archive->getStatusString());
        }
    }

    /**
     * Direct download to zip file
     *
     * @param $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadZip($id){
        $files = Storage::allFiles("temp_zips");
        foreach ($files as $file) {
            $fileId = explode('_',basename($file))[0];
            $fileName = explode('_',basename($file))[1];
            if ($fileId==$id){
                $path = Storage::disk('local')->getDriver()->getAdapter()->applyPathPrefix($file);
                $headers = array(
                    "Content-Disposition: attachment; filename=\"" . $fileName . "\"",
                    "Content-Type: application/force-download",
                    "Content-Length: " . filesize($path),
                    "Connection: close"
                );
                return response()->download($path, $fileName, $headers)->deleteFileAfterSend(true);
            }
        }
        abort(400, 'No zip file founded');
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
            'path' => 'required',
        ]);

        $path_array = explode('/',  $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        end($path_array);
        $key = key($path_array);
        reset($path_array);
        $oldPath = implode('/',$path_array);

        array_pop($path_array);
        array_push($path_array, $request->input('name'));
        $newPath = implode('/', $path_array);

        if (Storage::disk('files')->exists($newPath))
            abort(400, 'Directory with this name already exists');

        $files = Storage::disk('files')->allFiles($oldPath);
        foreach($files as $file){
            $fileEntry = Fileentry::where('path', $file)->first();
            if ($fileEntry){
                $file_path_array = explode('/',$fileEntry->path);
                if ($file_path_array[$key]){
                    $file_path_array[$key] = $request->input('name');
                }
                $fileEntry->path = implode('/', $file_path_array);
                $fileEntry->save();
            }
        }

        Storage::disk('files')->move($oldPath, $newPath);


        return "success";
    }

    /**
     * Rename a file
     *
     * @param Request $request
     * @return mixed
     */
    public function move(Request $request){
        $this->validate($request, [
            'oldPath' => 'required',
            'newPath' => 'required',
        ]);

        $old_path_array = explode('/', $request->input('oldPath'));
        $old_path_array = array_filter($old_path_array, function($value) { return $value !== ''; });
        array_unshift($old_path_array, Auth::id());
        $dirname = end($old_path_array);
        reset($old_path_array);
        $oldPath = implode('/', $old_path_array);

        $new_path_array = explode('/', $request->input('newPath'));
        $new_path_array = array_filter($new_path_array, function($value) { return $value !== ''; });
        array_push($new_path_array, $dirname);
        array_unshift($new_path_array, Auth::id());
        $newPath = implode('/', $new_path_array);

        $filesInside = Storage::disk('files')->allFiles($oldPath);

        if (!Storage::disk('files')->exists($newPath)){
            foreach($filesInside as $file){
                $fileEntry = Fileentry::where('path', $file)->first();
                if ($fileEntry){
                    $filePath = $newPath . '/' . implode('/',array_diff(explode('/',$fileEntry->path), explode('/', $oldPath)));
                    $fileEntry->path = $filePath;
                    $fileEntry->save();
                }
            }        
            Storage::disk('files')->move($oldPath, $newPath);
        }else{
            foreach($filesInside as $file){
                $fileEntry = Fileentry::where('path', $file)->first();
                if ($fileEntry){
                    $filePath = $newPath . '/' . implode('/',array_diff(explode('/',$fileEntry->path), explode('/', $oldPath)));
                    Storage::disk('files')->move($fileEntry->path, $filePath);
                    $fileEntry->path = $filePath;
                    $fileEntry->save();
                }
            }
            Storage::disk('files')->deleteDirectory($oldPath);
        }
        
        return "success";
    }

    /**
     * Delete a directory with his path
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request){
        $this->validate($request, [
            'path' => 'required',
        ]);

        $path_array = explode('/', $request->input('path'));
        $path_array = array_filter($path_array, function($value) { return $value !== ''; });
        array_unshift($path_array, Auth::id());
        $path = implode('/', $path_array);

        $files = Storage::disk('files')->allFiles($path);
        foreach($files as $file){
            Storage::disk('files')->delete($file);
            $fileEntry = Fileentry::where('path', $file)->first();
            if ($fileEntry){
                $fileEntry->delete();
            }
        }

        Storage::disk('files')->deleteDirectory($path);

        return [ "size" => Auth::user()->size()];
    }


 
}