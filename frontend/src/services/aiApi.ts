import axios from "axios";

export const aiApi = axios.create({
  baseURL: "http://localhost:3000",
});