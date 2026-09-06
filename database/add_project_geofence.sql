-- Geofencing: each project (site) gets a location and a radius. A worker may
-- only start at a site while physically inside that circle.
--
-- Radius is metres. geofence_enabled lets a site be exempt — head office, or a
-- site whose coordinates have not been surveyed yet — without deleting them.

ALTER TABLE projects ADD COLUMN latitude DECIMAL(10,7) DEFAULT NULL AFTER name;
ALTER TABLE projects ADD COLUMN longitude DECIMAL(10,7) DEFAULT NULL AFTER latitude;
ALTER TABLE projects ADD COLUMN geofence_radius INT DEFAULT 200 AFTER longitude;
ALTER TABLE projects ADD COLUMN geofence_enabled TINYINT(1) DEFAULT 0 AFTER geofence_radius;
ALTER TABLE projects ADD COLUMN location_label VARCHAR(255) DEFAULT NULL AFTER geofence_enabled;

-- Record where a site entry was actually started, so a supervisor can audit a
-- clock-in that was allowed, and see how far out it was.
ALTER TABLE attendance_site_entries ADD COLUMN start_latitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE attendance_site_entries ADD COLUMN start_longitude DECIMAL(10,7) DEFAULT NULL;
ALTER TABLE attendance_site_entries ADD COLUMN start_distance_m INT DEFAULT NULL;
