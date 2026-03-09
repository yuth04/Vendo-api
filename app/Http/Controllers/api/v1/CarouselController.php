<?php

namespace App\Http\Controllers\api\v1;

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

            'image' => 'required|image|max:5120',

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
            'image' => $upload->result->url,
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
            'image' => 'required|image|max:5120',
        ]);

        $file = $request->file('image');

        // Delete old image if exists
        if ($carousel->image_file_id) {
            try {
                $this->imageKit->deleteFile($carousel->image_file_id);
            } catch (\Exception $e) {
                \Log::error("ImageKit delete failed: " . $e->getMessage());
            }
        }

        // Upload new image
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
        // Update image 
        $carousel->image = $upload->result->url;
        $carousel->image_file_id = $upload->result->fileId;
        $carousel->save();

        return response()->json([
            'message' => 'Carousel image updated successfully',
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
