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


class MultipleController extends Controller {

    /**
     * Move a file/directory array
     *
     * @param Request $request
     * @return mixed
     */
    public function move(Request $request){
        $this->validate($request, [
            'files' => 'required',
            'newPath' => 'required',
        ]);

        $files = $request->input('files');
        foreach($files as $file){
            if (isset($file['path'])){
                $old_path_array = explode('/', $file['path']);
                $old_path_array = array_filter($old_path_array, function($value) { return $value !== ''; });
                array_shift($old_path_array);
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
            }else if (isset($file['hash'])){
                $entry = Fileentry::where('hash', $file['hash'])->first();
                if (!$entry) abort(400, 'File not found');
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
            }
        }

        return "success";
    }


    /**
     * Delete a file/ directory array
     *
     * @param Request $request
     * @return array
     */
    public function delete(Request $request){
        $this->validate($request, [
            'files' => 'required',
        ]);

        
        $files = $request->input('files');
        foreach($files as $file){
            if (isset($file['path'])){
                $path_array = explode('/', $file['path']);
                $path_array = array_filter($path_array, function($value) { return $value !== ''; });
                array_shift($path_array);
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
            }else if (isset($file['hash'])){
                $entry = Fileentry::where('hash', $file['hash'])->first();
                if ($entry->user_id != Auth::id()) abort(401, 'Unauthorized');
        
                Storage::disk('files')->delete($entry->path);
                $entry->delete();
            }
        }

        return [ "size" => Auth::user()->size()];
    }

}