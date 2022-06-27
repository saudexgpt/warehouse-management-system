<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Models\Stock\Category;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $categories = Category::/*with(['items'])->*/get();
        return response()->json(compact('categories'));
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Stock\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function show(Category $category)
    {
        return response()->json(compact('category'), 200);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

    public function store(Request $request, Category $category)
    {
        //
        $name = $request->name;
        $category = Category::where('name', $name)->first();

        if (!$category) {
            $category = new Category();
            $category->name = $name;
            $category->save();

            return $this->show($category);
        }
        $actor = $this->getUser();
        $title = "New product category added";
        $description = "New product category $name added by $actor->name ($actor->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        return response()->json(['message' => 'Duplicate Name'], 200);
    }



    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Category $category)
    {
        //
        $actor = $this->getUser();
        $title = "Product category updated";
        $description = "Product category $category->name updated by $actor->name ($actor->email) to $request->name";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        $category->name = $request->name;
        $category->save();

        return $this->show($category);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Category\Category  $category
     * @return \Illuminate\Http\Response
     */
    public function destroy(Category $category)
    {
        //
        $actor = $this->getUser();
        $title = "Product category deleted";
        $description = "Product category $category->name deleted by $actor->name ($actor->email)";
        //log this activity
        $roles = ['assistant admin', 'warehouse manager', 'warehouse auditor'];
        $this->logUserActivity($title, $description, $roles);
        $category->delete();
        return response()->json(null, 204);
    }
}
