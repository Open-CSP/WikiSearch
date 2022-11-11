-- Variable instantiation
local wikisearch = {}
local php

function wikisearch.setupInterface()
    -- Interface setup
    wikisearch.setupInterface = nil
    php = mw_interface
    mw_interface = nil

    -- Register library within the "mw.wikisearch" namespace
    mw = mw or {}
    mw.wikisearch = wikisearch

    package.loaded['mw.wikisearch'] = wikisearch
end

function wikisearch.propValues( parameters )
    if not type( parameters ) == 'table' then
        error( 'Invalid parameter type supplied to mw.wikisearch.propValues()' )
    end

    return php.propValues( parameters )
end

return wikisearch