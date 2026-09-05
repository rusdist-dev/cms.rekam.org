<div class="w-full sm:w-40">
    <label for="filter-status" class="sr-only">Status</label>
    <x-form.select id="filter-status" size="sm" placeholder="Semua status"
                   :options="config('cms.statuses')"
                   x-model="filters.status" @change="applyFilters()" />
</div>

<div class="w-full sm:w-44">
    <label for="filter-category" class="sr-only">Kategori</label>
    <x-form.remote-select
        id="filter-category"
        name="filter_category"
        :endpoint="route('dash-api.news-categories.index')"
        model="filters.category_id"
        value-key="id"
        label-key="name.{{ config('cms.default_locale') }}"
        placeholder="Semua kategori"
        empty-text="Belum ada kategori."
        error-text="Gagal memuat kategori."
        size="sm"
        @change="applyFilters()" />
</div>

@feature('news_programs')
    <div class="w-full sm:w-44">
        <label for="filter-program" class="sr-only">Program</label>
        <x-form.remote-select
            id="filter-program"
            name="filter_program"
            :endpoint="route('dash-api.taxonomy.show', 'news_programs')"
            model="filters.program"
            placeholder="Semua program"
            empty-text="Belum ada program."
            error-text="Gagal memuat program."
            size="sm"
            @change="applyFilters()" />
    </div>
@endfeature
