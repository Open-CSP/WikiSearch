CREATE TABLE /*_*/search_facets (
    page_id INT NOT NULL,
    property VARCHAR(255) NOT NULL,
    PRIMARY KEY (page_id, property)
)