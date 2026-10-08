import "./admin.scss";
import "./vendor/datastar.js";
import "./vendor/datastar-inspector.js";

import { root } from "./vendor/datastar.js";

window.Datastar = { root };

DatastarInspector.init({
	position: "right",
	width: "400px",
	height: "100vh",
	theme: "dark",
	startMinimized: true,
	hotkey: "Shift+D",
});
