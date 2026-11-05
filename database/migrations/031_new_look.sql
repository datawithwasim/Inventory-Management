-- The new look has a white menu. Companies still on the old dark default move to it (Settings → Appearance can switch back).
UPDATE settings SET svalue = 'light' WHERE skey = 'appearance.sidebar' AND svalue = 'dark';
