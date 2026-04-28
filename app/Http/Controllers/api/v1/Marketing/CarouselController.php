<?php

namespace App\Http\Controllers\api\v1\Marketing;

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
        $carousels = Carousel::where('status', true)
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


        $imageUrl = null;
        $imageFileId = null;

        if ($request->hasFile('image')) {


            $upload = $this->imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/carousels',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }

            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        $carousel = Carousel::create([
            'title' => $request->title,
            'description' => $request->description,
            'link' => $request->link,
            'position' => $request->position,
            'status' => $request->status ?? true,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
        ]);

        return response()->json([
            'message' => 'Carousel created successfully',
            'carousel' => $carousel
        ]);
    }
    // update
    public function update(Request $request, $id)
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

        $imageUrl = $carousel->image;
        $imageFileId = $carousel->image_file_id;

        if ($request->hasFile('image')) {


            $upload = $this->imageKit->upload([
                'file' => fopen($request->file('image')->getRealPath(), "r"),
                'fileName' => $request->file('image')->getClientOriginalName(),
                'folder' => '/carousels',
            ]);

            if (isset($upload->error)) {
                return response()->json([
                    'message' => 'Upload failed',
                    'error' => $upload->error->message,
                ], 400);
            }
            // DELETE OLD IMAGE (safe)
            if ($carousel->image_file_id) {
                try {
                    $this->imageKit->deleteFile($carousel->image_file_id);
                } catch (\Exception $e) {
                    // ignore if already deleted
                }
            }
            $imageUrl = $upload->result->url;
            $imageFileId = $upload->result->fileId;
        }

        $carousel->update([
            'title' => $request->title ?? $carousel->title,
            'description' => $request->description ?? $carousel->description,
            'link' => $request->link ?? $carousel->link,
            'position' => $request->position ?? $carousel->position,
            'status' => $request->status ?? $carousel->status,
            'image' => $imageUrl,
            'image_file_id' => $imageFileId,
        ]);

        return response()->json([
            'message' => 'Carousel updated successfully',
            'carousel' => $carousel
        ]);
    }
    // delete
    public function destroy($id)
    {
        $carousel = Carousel::findOrFail($id);
        if (!$carousel) {
            return response()->json([
                'message' => "Carousel id: $id not found"
            ], 404);
        }
        try {
            $imageKit = new ImageKit(
                config('imagekit.public_key'),
                config('imagekit.private_key'),
                config('imagekit.url_endpoint')
            );
            // Delete image from ImageKit
            if ($carousel->image_file_id) {
                $imageKit->deleteFile($carousel->image_file_id);
            }

            if ($carousel->images) {
                foreach ($carousel->images as $img) {
                    if (!empty($img->file_id)) {
                        $imageKit->deleteFile($img->file_id);
                    }
                }
            }
            $carousel->delete();
            DB::statement("SELECT setval('carousels_id_seq', COALESCE((SELECT MAX(id) FROM carousels), 0) + 1, false);");

            return response()->json([
                'message' => 'carousel deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'failed to delete carousel image',
                'error' => $e->getMessage()
            ], 500);
        }
        // Reset sequence (PostgreSQL only)

        return response()->json([
            'message' => 'Carousel deleted successfully'
        ]);
    }
}
