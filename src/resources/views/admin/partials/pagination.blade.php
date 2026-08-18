@if(isset($paginator) && $paginator->hasPages())
<div class="pagination-wrapper px-4 pt-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-3">
        <p class="pagination-summary mb-0">
            @if ($paginator->appends(request()->all()))
                {{ translate("Showing") }} {{ $paginator->appends(request()->all())->firstItem() }}-{{ $paginator->appends(request()->all())->lastItem() }} {{ translate("from") }} {{ $paginator->appends(request()->all())->total() }}
            @endif
        </p>
        <div class="d-flex align-items-center gap-2">
            <span class="fs-12 text-muted fw-medium">{{ translate("Per Page") }}:</span>
            <select class="form-select form-select-sm" style="width: auto; height: 30px; font-size: 12px; padding: 2px 24px 2px 8px; border-radius: 4px;" onchange="const url = new URL(window.location.href); url.searchParams.set('paginate', this.value); url.searchParams.set('page', 1); window.location.href = url.toString();">
                <option value="10" {{ request()->input('paginate', 10) == 10 ? 'selected' : '' }}>10</option>
                <option value="25" {{ request()->input('paginate') == 25 ? 'selected' : '' }}>25</option>
                <option value="50" {{ request()->input('paginate') == 50 ? 'selected' : '' }}>50</option>
                <option value="100" {{ request()->input('paginate') == 100 ? 'selected' : '' }}>100</option>
                <option value="200" {{ request()->input('paginate') == 200 ? 'selected' : '' }}>200</option>
                <option value="500" {{ request()->input('paginate') == 500 ? 'selected' : '' }}>500</option>
                <option value="all" {{ request()->input('paginate') == 'all' ? 'selected' : '' }}>{{ translate("All") }}</option>
            </select>
        </div>
    </div>
    <nav aria-label="...">
        <ul class="pagination">
            @if ($paginator->onFirstPage())
                <li class="page-item disabled">
                    <a class="page-link">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            @else
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
            @endif

            @foreach ($paginator->links()->elements as $element)
                @if(is_array($element))
                    @php $i = 1; @endphp
                    @foreach ($element as $url)
                        @php
                            if(request()->input("date")) {
                                $query_step = 4;
                            }
                            elseif(request()->input("search") || request()->input("status")) {
                                $query_step = 2;
                            } elseif(request()->_token) {
                                $query_step = 2;
                            } else {
                                $query_step = 1;
                            }
                            
                            $query_params = parse_url($url, PHP_URL_QUERY);
                            
                            $query_array  = $query_params ? explode('=', $query_params) : [];
                                
                            $page = isset($query_array[$query_step]) ? $query_array[$query_step] : (string)$i;
                            $i++;
                        @endphp
                        @if ($page == $paginator->currentPage())
                            <li class="page-item active" aria-current="page">
                                <span class="page-link">{{ $page }}</span>
                            </li>
                        @else
                            <li class="page-item">
                                <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                            </li>
                        @endif
                    @endforeach
                @else
                    <li class="page-item" aria-current="page">
                        <span class="page-link">{{ $element}}</span>
                    </li>
                @endif
            @endforeach
            
            @if ($paginator->hasMorePages())
                <li class="page-item">
                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            @else
                <li class="page-item disabled">
                    <a class="page-link">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            @endif
        </ul>
    </nav>
</div>
@endif