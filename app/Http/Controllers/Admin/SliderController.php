<?php

namespace App\Http\Controllers\Admin;

use App\Helper\CommonHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\MediaRequest;
use App\Http\Requests\SliderRequest;
use App\Models\Slider;
use App\Models\SliderMedia;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SliderController extends Controller
{
    public function index(Request $request)
    {
        $query = Slider::query()
            ->with('sliderMedias');

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereLike('title', "%$search%")
                ->orWhereLike('status', $search == 'active' ? 1 : ($search === 'inactive' ? 0 : null));
        }

        $sliders = $query->paginate(5)->withQueryString();

        return Inertia::render('Admin/Slider/Index', [
            'sliders' => $sliders,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Slider/Create');
    }

    public function save(SliderRequest $request)
    {
        $inputs = $request->validated();

        $slider = Slider::query()->create($inputs);

        return redirect()->route('admin.slider.edit', [$slider->id])->with('success', 'Slider Created Successfully');
    }

    public function edit(Slider $slider)
    {
        $slider->load('sliderMedias');

        return Inertia::render('Admin/Slider/Create', [
            'slider' => $slider,
        ]);
    }

    public function update(Slider $slider, SliderRequest $request)
    {
        $inputs = $request->validated();

        $slider->update($inputs);

        return redirect()->route('admin.sliders')->with('success', 'Slider Updated Successfully');
    }

    public function delete(Slider $slider)
    {
        try {
            $medias = $slider->sliderMedias()->get();
            foreach ($medias as $media) {
                if ($media->type == SliderMedia::IMAGE) {
                    CommonHelper::removeOldFile('public/slider/'.$media->url);
                }
            }
            $slider->delete();

            return redirect()->back()->with('success', 'Slider deleted successfully');
        } catch (\Exception $e) {
            $message = 'Something went wrong';
            if ($e->getCode() == 23000) {
                $message = 'slider associated with other records cannot be deleted';
            }

            return redirect()->back()->with('error', $message);
        }
    }

    public function saveMedia(Slider $slider, MediaRequest $request)
    {
        $inputs = $request->validated();

        if ($inputs['type'] == SliderMedia::VIDEO) {
            $inputs['url'] = $inputs['video_url'];
        } elseif ($inputs['type'] == SliderMedia::IMAGE) {
            $inputs['url'] = CommonHelper::uploadFile($request->file('image'), 'slider');
        }

        unset($inputs['video_url']);
        unset($inputs['image']);

        $slider->sliderMedias()->create($inputs);

        return redirect()->back()->with('success', 'Media added successfully');
    }

    public function updateMedia(Slider $slider, SliderMedia $media, MediaRequest $request)
    {
        $inputs = $request->validated();
        if ($inputs['type'] == SliderMedia::VIDEO) {
            $inputs['url'] = $inputs['video_url'];
            if (! empty($item->url)) {
                CommonHelper::removeOldFile('public/slider/'.$media->url);
                CommonHelper::removeOldFile('public/slider/file/'.$media->url);
            }
        } elseif ($inputs['type'] == SliderMedia::IMAGE) {
            if ($request->file('image')) {
                CommonHelper::removeOldFile('public/slider/'.$media->url);
                $inputs['url'] = CommonHelper::uploadFile($request->file('image'), 'slider');
            }
            // No new file uploaded — leave $media->url untouched by not setting $inputs['url'].
        }

        unset($inputs['image']);
        unset($inputs['video_url']);

        $media->update($inputs);

        return redirect()->back()->with('success', 'Media updated successfully');
    }

    public function deleteMedia(Slider $slider, SliderMedia $media)
    {
        if ($slider->sliderMedias()->count() <= 1) {
            return redirect()->back()->with('error', 'Only one media left, can not delete');
        }

        if ($media->type == SliderMedia::IMAGE) {
            CommonHelper::removeOldFile('public/slider/'.$media->url);
        }
        $media->delete();

        return redirect()->back()->with('success', 'Slider media Deleted successfully');
    }
}
