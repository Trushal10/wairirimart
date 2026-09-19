<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\HomeVideoRequest;
use App\Models\HomeVideo;
use Inertia\Inertia;

class HomeVideoController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/HomeVideo/Index', [
            'videos' => HomeVideo::query()->ordered()->get(),
        ]);
    }

    public function store(HomeVideoRequest $request)
    {
        $inputs = $request->safe()->except(['video', 'poster', 'remove_poster']);
        $inputs['priority'] = (int) ($inputs['priority'] ?? 0);
        $inputs['video'] = CommonHelper::uploadFile($request->file('video'), HomeVideo::DIR);
        if ($request->file('poster')) {
            $inputs['poster'] = CommonHelper::uploadFile($request->file('poster'), HomeVideo::DIR);
        }

        HomeVideo::query()->create($inputs);

        return redirect()->route('admin.home-videos')->with('success', 'Video added successfully');
    }

    public function update(HomeVideo $homeVideo, HomeVideoRequest $request)
    {
        $inputs = $request->safe()->except(['video', 'poster', 'remove_poster']);
        $inputs['priority'] = (int) ($inputs['priority'] ?? 0);

        // uploadFile() deletes the previous file when handed its name.
        if ($request->file('video')) {
            $inputs['video'] = CommonHelper::uploadFile($request->file('video'), HomeVideo::DIR, $homeVideo->video);
        }
        if ($request->file('poster')) {
            $inputs['poster'] = CommonHelper::uploadFile($request->file('poster'), HomeVideo::DIR, $homeVideo->poster);
        } elseif ($request->boolean('remove_poster') && $homeVideo->poster) {
            CommonHelper::removeOldFile('public/'.HomeVideo::DIR.'/'.$homeVideo->poster);
            $inputs['poster'] = null;
        }

        $homeVideo->update($inputs);

        return redirect()->route('admin.home-videos')->with('success', 'Video updated successfully');
    }

    public function delete(HomeVideo $homeVideo)
    {
        CommonHelper::removeOldFile('public/'.HomeVideo::DIR.'/'.$homeVideo->video);
        if ($homeVideo->poster) {
            CommonHelper::removeOldFile('public/'.HomeVideo::DIR.'/'.$homeVideo->poster);
        }
        $homeVideo->delete();

        return redirect()->back()->with('success', 'Video deleted successfully');
    }
}
