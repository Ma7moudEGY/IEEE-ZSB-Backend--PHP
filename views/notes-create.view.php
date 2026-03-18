<?php require "partials/header.php" ?>
<?php require "partials/nav.php" ?>
<?php require "partials/banner.php" ?>

<main>
    <form class="ml-7 mt-2 mr-7" method="POST">
    <div class="space-y-12">
        <div class="border-b border-white/10 pb-12">
            <div class="mt-10 grid grid-cols-1 gap-x-6 gap-y-8 sm:grid-cols-6">

                <div class="col-span-full">
                    <label for="body" class="block text-sm/6 font-medium text-white">Body</label>
                <div class="mt-2">
                    <textarea id="body" name="body" rows="3" class="block w-full rounded-md bg-white/5 px-3 py-1.5 text-base text-white outline-1 -outline-offset-1 outline-white/10 placeholder:text-gray-500 focus:outline-2 focus:-outline-offset-2 focus:outline-indigo-500 sm:text-sm/6"></textarea>
            </div>
        </div>
    </div>

  <div class="mt-6 flex items-center justify-end gap-x-6">
    <button type="submit" class="rounded-md bg-indigo-500 px-3 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">Save</button>
  </div>
</form>

</main>

<?php require "partials/foot.php" ?>