/**
 * Synced Patterns for Themes
 *
 * Teaches the editor the same trick the server side learns: a `core/pattern`
 * block can carry a `content` attribute that fills the pattern's slots, and a
 * pattern the theme marks as synced stays linked when it is inserted.
 */

import { extendPatternOverridesSource } from './pattern-overrides-source';

import './pattern-content-attribute';
import './pattern-content-edit';

extendPatternOverridesSource();
