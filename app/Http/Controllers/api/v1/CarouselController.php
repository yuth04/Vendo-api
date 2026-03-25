<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Carousel;
use Exception;
use Illuminate\Http\Request;
use ImageKit\ImageKit;
use Illuminate\Support\Facades\DB;
class CarouselController extends Controller
{
    protected $imageKit;

    public function __construct()
    {
        $this->imageKit = new ImageKit(
            config('imagekit.public_key'),
            config('imagekit.private_key'),
            config('imagekit.url_endpoint')
        );
    }
    public function index()
    {
        $carousels = Carousel::where('status', 1)
            ->orderBy('position')
            ->get();

        return response()->json([
            'message' => 'Carousels retrieved successfully',
            'carousels' => $carousels
        ]);
    }
    // create
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'image' => 'required|image|max:5120',
            'link' => 'nullable|string|max:255',
            'position' => 'nullable|boolean'

        ]);

        $file = $request->file('image');

        // Upload image to ImageKit
        $upload = $this->imageKit->upload([
            'file' => fopen($file->getRealPath(), 'r'),
            'fileName' => time() . '_' . $file->getClientOriginalName(),
            'folder' => '/carousels',
        ]);

        if (isset($upload->error)) {
            return response()->json([
                'message' => 'Image upload failed',
                'error' => $upload->error->message
            ], 400);
        }

        $carousel = Carousel::create([
            'title' => $request->title,
            'description' => $request->description,
            'image' => $upload->result->url,
            'link' => $request->link,
            'position' => $request->position,
            'status' => $request->status ?? true,
            'image_file_id' => $upload->result->fileId,
        ]);

        return response()->json([
            'message' => 'Carousel created successfully',
            'carousel' => $carousel
        ]);
    }
    // update
    public function updateImage(Request $request, $id)
    {
        $carousel = Carousel::findOrFail($id);

        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|max:5120', // max 5MB
            'link' => 'nullable|string|max:255',
            'position' => 'nullable|integer',
            'status' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // Delete old image from ImageKit
            if ($carousel->image_file_id) {
                try {
                    $this->imageKit->deleteFile($carousel->image_file_id);
                } catch (\Exception $e) {
                    \Log::error("ImageKit delete failed: " . $e->getMessage());
                }
            }

            // Upload new image to ImageKit
            $upload = $this->imageKit->upload([
                'file' => fopen($file->getRealPath(), 'r'),
                'fileName' => time() . '_' . $file->getClientOriginalName(),
                'folder' => '/carousels',
            ]);

            if (!empty($upload->error)) {
                return response()->json([
                    'message' => 'Image upload failed',
                    'error' => $upload->error
                ], 400);
            }

            // Save new image URL and file ID
            $carousel->image = $upload->result->url;
            $carousel->image_file_id = $upload->result->fileId;
        }

        // Update other fields if provided
        $carousel->title = $request->title ?? $carousel->title;
        $carousel->description = $request->description ?? $carousel->description;
        $carousel->link = $request->link ?? $carousel->link;
        $carousel->position = $request->position ?? $carousel->position;
        $carousel->status = $request->status ?? $carousel->status;

        $carousel->save();

        return response()->json([
            'message' => 'Carousel updated successfully',
            'carousel' => $carousel
        ]);
    }
    // delete
    public function destroy($id)
    {
        $carousel = Carousel::findOrFail($id);

        if ($carousel->image_file_id) {
            try {
                $this->imageKit->deleteFile($carousel->image_file_id);
            } catch (Exception $e) {
                // ignore errors
            }
        }

        $carousel->delete();

        // Reset sequence (PostgreSQL only)
        DB::statement("SELECT setval('carousels_id_seq', COALESCE((SELECT MAX(id) FROM carousels), 0) + 1, false);");

        return response()->json([
            'message' => 'Carousel deleted successfully'
        ]);
    }
}
